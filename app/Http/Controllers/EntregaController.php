<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\detalles_pedido;

class EntregaController extends Controller
{
    public function notificarRecolector(Request $request, $pedido_id)
    {
        $pedido = Pedido::find($pedido_id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        // Actualizar solo los productos listos del proveedor
        detalles_pedido::where('pedido_id', $pedido_id)
            ->whereHas('producto', function ($query) use ($request) {
                $query->where('proveedor_id', $request->proveedor_id);
            })
            ->update(['notificado_proveedor' => 1]);

        // Mensaje para la plataforma y WhatsApp
        $mensaje = "📦 Pedido #$pedido->id tiene productos listos para recoger.";

        $numeroRecolector = "51921013989"; // Reemplazar con número real
        $linkWhatsapp = "https://wa.me/$numeroRecolector?text=" . urlencode($mensaje);

        return response()->json([
            'message' => 'Productos listos para el recolector',
            'mensaje_plataforma' => $mensaje,
            'link_whatsapp' => $linkWhatsapp
        ]);
    }

    public function pedidosPorProveedor($id)
    {
        $pedidos = Pedido::whereHas('detalles_pedido', function ($query) use ($id) {
                $query->whereHas('producto', function ($subQuery) use ($id) {
                    $subQuery->where('proveedor_id', $id);
                })->where('notificado_proveedor', 0); // Excluir productos ya notificados
            })
            ->with([
                'detalles_pedido' => function ($query) use ($id) {
                    $query->whereHas('producto', function ($subQuery) use ($id) {
                        $subQuery->where('proveedor_id', $id);
                    })->where('notificado_proveedor', 0); // Excluir productos ya notificados
                },
                'detalles_pedido.producto',
                'user:id,name'
            ])
            ->orderBy('created_at', 'asc')
            ->get();
    
        return response()->json($pedidos);
    }
    
}
