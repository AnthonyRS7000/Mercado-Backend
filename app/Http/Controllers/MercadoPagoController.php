<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        Log::info('Preferencia creada en MercadoPago', [
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
        Log::info('Webhook MercadoPago recibido', ['data' => $data]);

        $topic = $data['topic'] ?? $data['type'] ?? null;

        SDK::setAccessToken(config('services.mercadopago.token'));

        if ($topic === 'payment') {
            $paymentId = data_get($data, 'data.id') ?? data_get($data, 'id');
            Log::info("Procesando webhook de tipo payment", ['paymentId' => $paymentId]);
            if ($paymentId) {
                $this->procesarPago($paymentId);
            }
        }

        if ($topic === 'merchant_order') {
            $orderId = $data['id'] ?? null;
            Log::info("Procesando webhook de tipo merchant_order", ['orderId' => $orderId]);
            if ($orderId) {
                $order = MerchantOrder::find_by_id($orderId);
                if ($order && $order->payments) {
                    foreach ($order->payments as $pay) {
                        Log::info("Pago encontrado en merchant_order", (array) $pay);
                        if ($pay->status === 'approved') {
                            $this->procesarPago($pay->id);
                        }
                    }
                }
            }
        }

        return response()->json(['msg' => 'ok'], 200);
    }

    /**
     * Procesar pago aprobado y crear pedido.
     */
    private function procesarPago($paymentId)
    {
        Log::info("🔎 Iniciando procesamiento de pago", ['paymentId' => $paymentId]);

        $payment = Payment::find_by_id($paymentId);

        if (!$payment) {
            Log::error("❌ Pago no encontrado en MP", ['paymentId' => $paymentId]);
            return;
        }

        Log::info("Datos del pago recibido", [
            'id'       => $payment->id,
            'status'   => $payment->status,
            'amount'   => $payment->transaction_amount,
            'order_id' => $payment->order->id ?? null,
        ]);

        if ($payment->status !== 'approved') {
            Log::warning('⚠️ Pago no aprobado', ['paymentId' => $paymentId, 'status' => $payment->status]);
            return;
        }

        if (Pago::where('mp_payment_id', $payment->id)->exists()) {
            Log::warning("⚠️ Pago duplicado detectado", ['paymentId' => $payment->id]);
            return;
        }

        // Recuperar metadata
        $meta = [];
        $prefId = $payment->preference_id; // ✅ usar solo preference_id

        try {
            Log::info("Buscando preferencia asociada", ['prefId' => $prefId]);

            if ($prefId) {
                $pref = Preference::find_by_id($prefId);
                if ($pref && isset($pref->metadata)) {
                    $meta = (array) $pref->metadata;
                }
            }
        } catch (\Exception $e) {
            Log::error("❌ Error recuperando preferencia de MP", ['error' => $e->getMessage()]);
        }

        Log::info("Metadata recuperada", ['meta' => $meta]);

        // Crear pedido
        $pedido = Pedido::create([
            'fecha'             => now()->toDateString(),
            'estado'            => 1,
            'direccion_entrega' => $meta['direccion_entrega'] ?? 'NO DEFINIDA',
            'user_id'           => $meta['user_id'] ?? null,
            'metodo_pago_id'    => 2,
            'total'             => 0,
            'fecha_programada'  => $meta['fecha_programada'] ?? null,
            'hora_programada'   => $meta['hora_programada'] ?? null,
        ]);

        Log::info("🛒 Pedido creado provisionalmente", ['pedidoId' => $pedido->id]);

        // Detalles desde carrito
        $total = 0;
        $carritos = Carrito::with('productos')
            ->where('user_id', $pedido->user_id)
            ->get();

        foreach ($carritos as $carrito) {
            foreach ($carrito->productos as $producto) {
                $subtotal = $producto->precio * $producto->pivot->cantidad;

                DetallesPedido::create([
                    'pedido_id'       => $pedido->id,
                    'producto_id'     => $producto->id,
                    'cantidad'        => $producto->pivot->cantidad,
                    'precio_unitario' => $producto->precio,
                    'subtotal'        => $subtotal,
                ]);

                $total += $subtotal;
            }
        }

        $pedido->total = $total;
        $pedido->save();

        Log::info("✅ Pedido actualizado con total", ['pedidoId' => $pedido->id, 'total' => $total]);

        Pago::create([
            'pedido_id'          => $pedido->id,
            'user_id'            => $pedido->user_id,
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

        Carrito::where('user_id', $pedido->user_id)->delete();

        Log::info("🧹 Carrito vaciado", ['userId' => $pedido->user_id]);

        Log::info("🎉 Pedido finalizado con éxito", [
            'pedidoId'     => $pedido->id,
            'mp_payment_id'=> $payment->id
        ]);
    }
}
