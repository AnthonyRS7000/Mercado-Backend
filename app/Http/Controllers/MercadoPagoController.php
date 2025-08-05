<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Pedido;
use App\Models\detalles_pedido;
use App\Models\Producto;
use App\Models\Pago; // Nuevo modelo Pago
use MercadoPago\SDK;
use MercadoPago\Item;
use MercadoPago\Preference;
use MercadoPago\Payment;

class MercadoPagoController extends Controller
{
    /**
     * Crea una preferencia de Mercado Pago sin registrar el pedido.
     * El pedido se creará después de que el usuario complete el pago (webhook).
     */
    public function crearPreferencia(Request $request)
    {
        // 0) Validación
        $v = Validator::make($request->all(), [
            'user_id'                 => 'required|exists:users,id',
            'direccion_entrega'       => 'required|string|max:255',
            'productos'               => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.cantidad'    => 'required|numeric|min:0.1',
            'fecha_programada'        => 'nullable|date|after_or_equal:today',
            'hora_programada'         => 'nullable|date_format:H:i',
        ]);

        if ($v->fails()) {
            Log::warning('Validation failed (crearPreferencia)', ['errors' => $v->errors()->toArray()]);
            return response()->json(['errors' => $v->errors()], 422);
        }

        // 1) Credenciales sandbox vs prod
        $svc = config('services.mercadopago');
        if (config('app.env') === 'local') {
            $clientId     = $svc['client_id_test'];
            $clientSecret = $svc['client_secret_test'];
            $accessToken  = $svc['access_token_test'];
        } else {
            $clientId     = $svc['client_id'];
            $clientSecret = $svc['client_secret'];
            $accessToken  = $svc['access_token'];
        }

        SDK::setClientId($clientId);
        SDK::setClientSecret($clientSecret);
        SDK::setAccessToken($accessToken);

        Log::info('Using MP Credentials', [
            'env'          => config('app.env'),
            'client_id'    => substr($clientId, -6),
            'access_token' => substr($accessToken, -6),
        ]);

        // 2) Construir items
        $items = [];
        foreach ($request->productos as $p) {
            $prod = Producto::findOrFail($p['producto_id']);
            $item = new Item();
            $item->title       = $prod->nombre;
            $item->quantity    = (int) $p['cantidad'];
            $item->unit_price  = (float) $prod->precio;
            $item->currency_id = 'PEN';
            $items[] = $item;
        }

        // 3) Crear preferencia
        $pref = new Preference();
        $pref->items             = $items;
        $pref->back_urls         = [
            'success' => config('app.frontend_url') . '/pago-exitoso',
            'failure' => config('app.frontend_url') . '/pago-fallido',
            'pending' => config('app.frontend_url') . '/pago-pendiente',
        ];
        $pref->auto_return       = 'approved';
        $pref->metadata          = [
            'user_id'           => $request->user_id,
            'direccion_entrega' => $request->direccion_entrega,
            'productos'         => json_encode($request->productos),
            'fecha_programada'  => $request->fecha_programada,
            'hora_programada'   => $request->hora_programada,
        ];
        $pref->notification_url  = config('app.url') . '/api/mp/webhook';
        $pref->save();

        Log::info('MP Preference Created', [
            'id'                 => $pref->id,
            'init_point'         => $pref->init_point,
            'sandbox_init_point' => $pref->sandbox_init_point,
        ]);

        $url = $pref->sandbox_init_point ?: $pref->init_point;
        return response()->json([
            'preference_id' => $pref->id,
            'init_point'    => $url,
        ], 200);
    }

    /**
     * Webhook para procesar pagos de Mercado Pago.
     * Crea el pedido y registra el pago si fue aprobado.
     */
public function webhook(Request $request)
{
    $data = $request->all();
    Log::info('MP Webhook received', ['data' => $data]);

    // Token test vs prod
    $svc   = config('services.mercadopago');
    $token = config('app.env') === 'local'
        ? $svc['access_token_test']
        : $svc['access_token'];

    Log::info('AccessToken utilizado', ['token' => $token]);

    SDK::setAccessToken($token);

    // Ignorar si no es pago
    if (data_get($data, 'type') !== 'payment') {
        Log::warning('Webhook ignored: not a payment event', ['type' => data_get($data,'type')]);
        return response()->json(['msg'=>'not payment event'], 200);
    }

    // ID de pago
    $paymentId = data_get($data, 'data.id');
    Log::info('Parsed payment ID from webhook', ['payment_id' => $paymentId]);

    if (!$paymentId) {
        Log::error('Webhook error: data.id missing', ['request' => $data]);
        return response()->json(['msg'=>'no payment id'], 200);
    }

    // -------------- CONSULTA DIRECTA API REST MP (Debug extra) --------------
    $urlApi = "https://api.mercadopago.com/v1/payments/$paymentId?access_token=$token";
    try {
        $respuestaDirecta = file_get_contents($urlApi);
        Log::info('Respuesta directa de la API de MP', ['url' => $urlApi, 'response' => $respuestaDirecta]);
    } catch (\Exception $e) {
        Log::error('Error al consultar la API de MP directamente', ['exception' => $e->getMessage(), 'url' => $urlApi]);
    }
    // -------------------------------------------------------------------------

    // Implementa RETRY para obtener Payment por SDK
    $maxAttempts = 3;
    $attempt = 0;
    $payment = null;
    do {
        $attempt++;
        try {
            $payment = Payment::find_by_id($paymentId);
            Log::info("Intento $attempt de obtener Payment", ['payment' => (array)$payment]);
            if ($payment && isset($payment->status)) {
                break;
            }
        } catch (\Exception $e) {
            Log::error("Error en intento $attempt al buscar Payment", ['exception' => $e->getMessage()]);
        }
        sleep(2); // espera 2 segundos antes de reintentar
    } while ($attempt < $maxAttempts);

    if (!$payment || !isset($payment->status)) {
        Log::error('Payment object is null o vacío tras retries', ['paymentId' => $paymentId, 'intentos' => $attempt]);
        return response()->json(['msg'=>'null payment'], 200);
    }

    if ($payment->status !== 'approved') {
        Log::warning('Payment not approved', ['status'=> $payment->status]);
        return response()->json(['msg'=>'not approved'], 200);
    }
    Log::info('Payment status approved', ['status'=> $payment->status]);

    // Obtener preferencia y datos para el pedido
    try {
        $pref  = Preference::find_by_id($payment->preference_id);
        Log::info('Preference fetched', ['pref' => (array)$pref]);
        $meta  = (array) $pref->metadata;
        $productos  = json_decode($meta['productos'] ?? '[]', true);
        Log::info('Decoded productos', ['productos' => $productos, 'meta' => $meta]);
    } catch (\Exception $e) {
        Log::error('Error decoding preference or productos', ['exception' => $e->getMessage()]);
        return response()->json(['msg'=>'decode error'], 200);
    }

    // Crear pedido
    try {
        $pedido = Pedido::create([
            'fecha'             => now()->toDateString(),
            'estado'            => 1,
            'direccion_entrega' => $meta['direccion_entrega'] ?? null,
            'user_id'           => $meta['user_id'] ?? null,
            'metodo_pago_id'    => 2, // Fijo para Mercado Pago
            'total'             => $payment->transaction_amount,
            'fecha_programada'  => $meta['fecha_programada'] ?? null,
            'hora_programada'   => $meta['hora_programada']  ?? null,
        ]);
        Log::info('Pedido creado tras pago aprobado', ['pedido_id' => $pedido->id]);
    } catch (\Exception $e) {
        Log::error('Error creando pedido', ['exception' => $e->getMessage(), 'meta' => $meta]);
        return response()->json(['msg'=>'pedido error'], 200);
    }

    // Detalles
    try {
        foreach ($productos as $p) {
            $prod     = Producto::findOrFail($p['producto_id']);
            $subtotal = $prod->precio * $p['cantidad'];
            detalles_pedido::create([
                'pedido_id'       => $pedido->id,
                'producto_id'     => $prod->id,
                'cantidad'        => $p['cantidad'],
                'precio_unitario' => $prod->precio,
                'subtotal'        => $subtotal,
            ]);
        }
        Log::info('Detalles de pedido creados');
    } catch (\Exception $e) {
        Log::error('Error creando detalles de pedido', ['exception' => $e->getMessage()]);
        return response()->json(['msg'=>'detalle pedido error'], 200);
    }

    // Crear registro de pago en la tabla pagos
    try {
        Pago::create([
            'pedido_id'         => $pedido->id,
            'user_id'           => $pedido->user_id,
            'monto'             => $payment->transaction_amount,
            'metodo_pago'       => 2, // Mercado Pago
            'mp_payment_id'     => $payment->id,
            'mp_preference_id'  => $payment->preference_id,
            'mp_status'         => $payment->status,
            'mp_status_detail'  => $payment->status_detail,
            'mp_payment_type_id'=> $payment->payment_type_id,
            'mp_installments'   => $payment->installments,
            'mp_card_issuer_id' => $payment->card_issuer_id,
            'mp_card_id'        => $payment->card_id,
            'mp_raw_response'   => json_encode($payment),
        ]);
        Log::info('Pago guardado en tabla pagos', ['pedido_id' => $pedido->id, 'payment_id' => $payment->id]);
    } catch (\Exception $e) {
        Log::error('Error guardando Pago en tabla pagos', ['exception' => $e->getMessage()]);
        return response()->json(['msg'=>'pago save error'], 200);
    }

    return response()->json(['msg'=>'ok'], 200);
}

}
