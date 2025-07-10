<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
require_once base_path('vendor/autoload.php');

class MercadoPagoController extends Controller
{
    public function crearPreferencia(Request $request)
    {
        // Configura tu Access Token de Mercado Pago
        \MercadoPago\SDK::setAccessToken(env('MP_ACCESS_TOKEN'));


        // Recibe los items desde el frontend (array con title, quantity, unit_price)
        $items = [];
        foreach ($request->input('items') as $item) {
            $item_mp = new \MercadoPago\Item();
            $item_mp->title = $item['title'];
            $item_mp->quantity = $item['quantity'];
            $item_mp->unit_price = $item['unit_price'];
            $items[] = $item_mp;
        }

        // Crea la preferencia
        $preference = new \MercadoPago\Preference();
        $preference->items = $items;
        $preference->back_urls = [
            "success" => "https://tu-frontend.com/pago-exitoso",
            "failure" => "https://tu-frontend.com/pago-fallido",
            "pending" => "https://tu-frontend.com/pago-pendiente"
        ];
        $preference->auto_return = "approved";
        $preference->save();

        return response()->json([
            "id" => $preference->id,
            "init_point" => $preference->init_point
        ]);
    }
}
