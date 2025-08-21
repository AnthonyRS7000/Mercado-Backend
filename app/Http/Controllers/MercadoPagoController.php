<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Pedido;
use App\Models\DetallesPedido;
use App\Models\Producto;
use App\Models\Pago;
use App\Models\Carrito;
use MercadoPago\SDK;
use MercadoPago\Item;
use MercadoPago\Preference;
use MercadoPago\Payment;
use MercadoPago\MerchantOrder;

class MercadoPagoController extends Controller
{
    /**
     * Crear preferencia de pago en MercadoPago.
     */
    public function crearPreferencia(Request $request)
    {
        $request->validate([
            'user_id'           => 'required|exists:users,id',
            'direccion_entrega' => 'required|string|max:255',
            'fecha_programada'  => 'nullable|date|after_or_equal:today',
            'hora_programada'   => 'nullable|date_format:H:i',
        ]);

        SDK::setAccessToken(config('services.mercadopago.token'));

        $carritos = Carrito::with('productos')
            ->where('user_id', $request->user_id)
            ->get();

        if ($carritos->isEmpty()) {
            return response()->json(['error' => 'El carrito está vacío'], 400);
        }

        $items = [];
        foreach ($carritos as $carrito) {
            foreach ($carrito->productos as $producto) {
                $item = new Item();
                $item->title       = $producto->nombre;
                $item->quantity    = (int) $producto->pivot->cantidad;
                $item->unit_price  = (float) $producto->precio;
                $item->currency_id = 'PEN';
                $items[] = $item;
            }
        }

        $pref = new Preference();
        $pref->items = $items;
        $pref->back_urls = [
            'success' => config('app.frontend_url') . '/mp/success',
            'failure' => config('app.frontend_url') . '/mp/failure',
            'pending' => config('app.frontend_url') . '/mp/pending',
        ];
        $pref->auto_return = 'approved';
        $pref->metadata = [
            'user_id'           => $request->user_id,
            'direccion_entrega' => $request->direccion_entrega,
            'fecha_programada'  => $request->fecha_programada,
            'hora_programada'   => $request->hora_programada,
        ];
        $pref->notification_url = config('app.url') . '/api/mp/webhook';
        $pref->save();

        Log::info('✅ Preferencia creada en MercadoPago', [
            'pref_id'    => $pref->id,
            'init_point' => $pref->init_point,
            'metadata'   => (array) $pref->metadata,
        ]);

        return response()->json([
            'preference_id' => $pref->id,
            'init_point'    => $pref->init_point,
        ], 200);
    }

    /**
     * Webhook de MercadoPago.
     */
    public function webhook(Request $request)
    {
        $data = $request->all();
        Log::info('🌐 Webhook MercadoPago recibido', ['data' => $data]);

        $topic = $data['topic'] ?? $data['type'] ?? null;

        SDK::setAccessToken(config('services.mercadopago.token'));

        // Solo procesar webhooks de payment
        if ($topic === 'payment') {
            $paymentId = data_get($data, 'data.id') ?? data_get($data, 'id');
            Log::info("🔔 Procesando webhook de tipo payment", ['paymentId' => $paymentId]);

            if ($paymentId) {
                // Verificación simple de duplicados recientes
                $procesamientoReciente = Pago::where('mp_payment_id', $paymentId)
                    ->where('created_at', '>', now()->subMinutes(2))
                    ->exists();

                if ($procesamientoReciente) {
                    Log::info("🔒 Pago procesado recientemente, omitiendo", ['paymentId' => $paymentId]);
                    return response()->json(['msg' => 'already_processed'], 200);
                }

                try {
                    $this->procesarPago($paymentId);
                } catch (\Exception $e) {
                    Log::error("❌ Error procesando pago", [
                        'paymentId' => $paymentId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }

        // Comentamos el procesamiento de merchant_order para evitar duplicados
        /*
        if ($topic === 'merchant_order') {
            $orderId = $data['id'] ?? null;
            Log::info("🔔 Procesando webhook de tipo merchant_order", ['orderId' => $orderId]);
            // ... código comentado
        }
        */

        return response()->json(['msg' => 'ok'], 200);
    }

    /**
     * Procesar pago aprobado y crear pedido.
     */
    private function procesarPago($paymentId)
    {
        Log::info("🔎 Iniciando procesamiento de pago", ['paymentId' => $paymentId]);

        // PRIMERA VERIFICACIÓN: Evitar duplicados desde el inicio
        $pagoExistente = Pago::where('mp_payment_id', $paymentId)->first();
        if ($pagoExistente) {
            Log::warning("⚠️ Pago ya procesado anteriormente, se omite", [
                'paymentId' => $paymentId,
                'pedidoId' => $pagoExistente->pedido_id
            ]);
            return;
        }

        $payment = Payment::find_by_id($paymentId);

        if (!$payment) {
            Log::error("❌ Pago no encontrado en MP", ['paymentId' => $paymentId]);
            return;
        }

        Log::info("📄 Datos del pago recibido", [
            'id'       => $payment->id,
            'status'   => $payment->status,
            'amount'   => $payment->transaction_amount,
            'order_id' => $payment->order->id ?? null,
        ]);

        if ($payment->status !== 'approved') {
            Log::warning('⚠️ Pago no aprobado, no se genera pedido', [
                'paymentId' => $paymentId,
                'status'    => $payment->status
            ]);
            return;
        }

        // --------------------------------------
        // Recuperar metadata desde preference_id
        // --------------------------------------
        $meta = [];
        $prefId = null;

        try {
            $paymentArray = json_decode(json_encode($payment), true);
            $prefId = $paymentArray['preference_id'] ?? null;

            // fallback: obtener desde merchant_order
            if (!$prefId && isset($payment->order->id)) {
                $order = MerchantOrder::find_by_id($payment->order->id);
                $prefId = $order->preference_id ?? null;
            }

            Log::info("🔗 Buscando preferencia asociada", ['prefId' => $prefId]);

            if ($prefId) {
                $pref = Preference::find_by_id($prefId);
                if ($pref && isset($pref->metadata)) {
                    $meta = (array) $pref->metadata;
                }
            }
        } catch (\Exception $e) {
            Log::error("❌ Error recuperando preferencia de MP", ['error' => $e->getMessage()]);
        }

        Log::info("📦 Metadata recuperada", ['meta' => $meta]);

        $userId = $meta['user_id'] ?? null;

        if (!$userId) {
            Log::error("❌ No se pudo obtener user_id de los metadatos", ['meta' => $meta]);
            return;
        }

        // --------------------------------------
        // CRÍTICO: Verificar que el carrito tenga productos ANTES de crear el pedido
        // --------------------------------------
        $carritos = Carrito::with('productos')
            ->where('user_id', $userId)
            ->get();

        if ($carritos->isEmpty()) {
            Log::error("❌ El carrito está vacío para el usuario, no se puede crear pedido", [
                'userId' => $userId,
                'paymentId' => $paymentId
            ]);
            return;
        }

        // Verificar que efectivamente tenga productos
        $tieneProductos = false;
        foreach ($carritos as $carrito) {
            if ($carrito->productos->count() > 0) {
                $tieneProductos = true;
                break;
            }
        }

        if (!$tieneProductos) {
            Log::error("❌ El carrito no tiene productos, no se puede crear pedido", [
                'userId' => $userId,
                'paymentId' => $paymentId
            ]);
            return;
        }

        // --------------------------------------
        // Verificar si ya existe un pedido para este pago (método alternativo)
        // --------------------------------------
        $pagoExistente = Pago::where('mp_payment_id', $paymentId)->first();

        if ($pagoExistente) {
            Log::warning("⚠️ Ya existe un pedido para este pago", [
                'paymentId' => $paymentId,
                'pedidoId' => $pagoExistente->pedido_id
            ]);
            return;
        }

        // --------------------------------------
        // Crear pedido
        // --------------------------------------
        $pedido = Pedido::create([
            'fecha'             => now()->toDateString(),
            'estado'            => 1,
            'direccion_entrega' => $meta['direccion_entrega'] ?? 'NO DEFINIDA',
            'user_id'           => $userId,
            'metodo_pago_id'    => 2,
            'total'             => 0,
            'fecha_programada'  => $meta['fecha_programada'] ?? null,
            'hora_programada'   => $meta['hora_programada'] ?? null,
        ]);

        Log::info("🛒 Pedido creado provisionalmente", ['pedidoId' => $pedido->id]);

        // --------------------------------------
        // Crear detalles desde carrito
        // --------------------------------------
        $total = 0;

        foreach ($carritos as $carrito) {
            foreach ($carrito->productos as $producto) {
                $cantidad = $producto->pivot->cantidad;
                $precioUnitario = $producto->precio;
                $subtotal = $precioUnitario * $cantidad;

                DetallesPedido::create([
                    'pedido_id'       => $pedido->id,
                    'producto_id'     => $producto->id,
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'subtotal'        => $subtotal,
                ]);

                $total += $subtotal;

                Log::info("📝 Detalle agregado", [
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $subtotal
                ]);
            }
        }

        // Actualizar total del pedido
        $pedido->total = $total;
        $pedido->save();

        Log::info("✅ Pedido actualizado con total", ['pedidoId' => $pedido->id, 'total' => $total]);

        // --------------------------------------
        // Registrar pago
        // --------------------------------------
        Pago::create([
            'pedido_id'          => $pedido->id,
            'user_id'            => $userId,
            'monto'              => $payment->transaction_amount,
            'metodo_pago'        => 2,
            'mp_payment_id'      => $payment->id,
            'mp_preference_id'   => $prefId ?? null,
            'mp_status'          => $payment->status,
            'mp_status_detail'   => $payment->status_detail,
            'mp_payment_type_id' => $payment->payment_type_id,
            'mp_installments'    => $payment->installments,
            'mp_raw_response'    => json_encode($payment),
        ]);

        Log::info("💵 Pago registrado en BD", ['paymentId' => $payment->id]);

        // --------------------------------------
        // Vaciar carrito AL FINAL y solo si todo fue exitoso
        // --------------------------------------
        $carritoEliminados = Carrito::where('user_id', $userId)->delete();

        Log::info("🧹 Carrito vaciado", [
            'userId' => $userId,
            'registrosEliminados' => $carritoEliminados
        ]);

        Log::info("🎉 Pedido finalizado con éxito", [
            'pedidoId'      => $pedido->id,
            'mp_payment_id' => $payment->id,
            'total'         => $total,
            'detalles_count' => DetallesPedido::where('pedido_id', $pedido->id)->count()
        ]);
    }
}
