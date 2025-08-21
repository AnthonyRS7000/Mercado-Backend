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

class MercadoPagoController extends Controller
{
    /**
     * Crea una preferencia de Mercado Pago a partir del carrito del usuario.
     */
    public function crearPreferencia(Request $request)
    {
        $request->validate([
            'user_id'           => 'required|exists:users,id',
            'direccion_entrega' => 'required|string|max:255',
            'fecha_programada'  => 'nullable|date|after_or_equal:today',
            'hora_programada'   => 'nullable|date_format:H:i',
        ]);

        // Credenciales
        SDK::setAccessToken(config('services.mercadopago.token'));

        // Obtener carrito con productos
        $carritos = Carrito::with('productos')
            ->where('user_id', $request->user_id)
            ->get();

        if ($carritos->isEmpty()) {
            return response()->json(['error' => 'El carrito está vacío'], 400);
        }

        // Construir items
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

        // Crear preferencia
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
        ]);

        return response()->json([
            'preference_id' => $pref->id,
            'init_point'    => $pref->init_point,
        ], 200);
    }

    /**
     * Webhook para procesar pagos de Mercado Pago.
     */
    public function webhook(Request $request)
    {
        $data = $request->all();
        Log::info('Webhook MercadoPago recibido', ['data' => $data]);

        if (data_get($data, 'type') !== 'payment') {
            return response()->json(['msg' => 'not payment event'], 200);
        }

        $paymentId = data_get($data, 'data.id');
        if (!$paymentId) {
            return response()->json(['msg' => 'no payment id'], 200);
        }

        SDK::setAccessToken(config('services.mercadopago.token'));
        $payment = Payment::find_by_id($paymentId);

        if (!$payment || $payment->status !== 'approved') {
            return response()->json(['msg' => 'not approved'], 200);
        }

        // ⚠️ Evitar duplicados: si ya existe un pago con ese id, no crear de nuevo
        if (Pago::where('mp_payment_id', $payment->id)->exists()) {
            Log::warning("Pago duplicado detectado, id={$payment->id}");
            return response()->json(['msg' => 'duplicate payment ignored'], 200);
        }

        // Obtener metadata de la preferencia
        $pref = Preference::find_by_id($payment->preference_id);
        $meta = (array) $pref->metadata;

        // Crear pedido
        $pedido = Pedido::create([
            'fecha'             => now()->toDateString(),
            'estado'            => 1, // pendiente
            'direccion_entrega' => $meta['direccion_entrega'] ?? null,
            'user_id'           => $meta['user_id'] ?? null,
            'metodo_pago_id'    => 2, // Mercado Pago
            'total'             => $payment->transaction_amount,
            'fecha_programada'  => $meta['fecha_programada'] ?? null,
            'hora_programada'   => $meta['hora_programada'] ?? null,
        ]);

        // Crear detalles desde carrito
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
            }
        }

        // Registrar pago
        Pago::create([
            'pedido_id'          => $pedido->id,
            'user_id'            => $pedido->user_id,
            'monto'              => $payment->transaction_amount,
            'metodo_pago'        => 2,
            'mp_payment_id'      => $payment->id,
            'mp_preference_id'   => $payment->preference_id,
            'mp_status'          => $payment->status,
            'mp_status_detail'   => $payment->status_detail,
            'mp_payment_type_id' => $payment->payment_type_id,
            'mp_installments'    => $payment->installments,
            'mp_raw_response'    => json_encode($payment),
        ]);

        // Vaciar carrito
        Carrito::where('user_id', $pedido->user_id)->delete();

        Log::info("Pedido {$pedido->id} creado con pago aprobado", [
            'mp_payment_id' => $payment->id,
        ]);

        return response()->json(['msg' => 'ok'], 200);
    }
}
