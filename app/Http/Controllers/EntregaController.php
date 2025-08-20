<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\Entrega;
use App\Models\DetallesPedido; // ✅ modelo corregido
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class EntregaController extends Controller
{
    public function store(Request $request)
    {
        Log::info('Iniciando la creación de entrega', ['request_data' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'fecha_entrega'   => 'required|date',
            'imagen_entregas' => 'required|file|max:2048',
            'comentario'      => 'required|string',
            'estado'          => 'required|integer|in:0,1,2,3',
            'precio'          => 'required|numeric',
            'pedido_id'       => 'required|exists:pedidos,id',
            'delivery_id'     => 'required|exists:deliveries,id'
        ]);

        if ($validator->fails()) {
            Log::error('Error de validación al crear entrega', ['errors' => $validator->errors()]);
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('imagen_entregas')) {
            Log::info('Imagen detectada');
            $file = $request->file('imagen_entregas');
            $mimeType = $file->getClientMimeType();
            Log::info('Tipo MIME de la imagen de entrega:', [$mimeType]);

            $validMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            if (!in_array($mimeType, $validMimeTypes)) {
                Log::error('Tipo de archivo no permitido:', [$mimeType]);
                return response()->json(['error' => 'El tipo de archivo no es permitido'], 422);
            }

            $imagePath = $file->store('entregas', 'public');
            $imageUrl = Storage::url($imagePath);
        } else {
            Log::error('No se detectó una imagen en la solicitud');
            return response()->json(['error' => 'El campo imagen_entregas es obligatorio'], 400);
        }

        try {
            $entrega = Entrega::create([
                'fecha_entrega'   => $request->fecha_entrega,
                'imagen_entregas' => $imageUrl ?? null,
                'comentario'      => $request->comentario,
                'estado'          => (int) $request->estado,
                'precio'          => $request->precio,
                'pedido_id'       => $request->pedido_id,
                'delivery_id'     => $request->delivery_id
            ]);

            // ✅ Cambiar estado del pedido a 5 (entregado)
            $pedido = Pedido::find($request->pedido_id);
            if ($pedido) {
                $pedido->estado = 5;
                $pedido->save();
                Log::info('Estado del pedido actualizado a 5', ['pedido_id' => $pedido->id]);
            }

            return response()->json([
                'message' => 'Entrega creada con éxito y estado del pedido actualizado',
                'data'    => $entrega
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear la entrega', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString()
            ]);
            return response()->json([
                'error'    => 'Error al crear la entrega',
                'detalles' => $e->getMessage() // 👈 aquí verás el error real
            ], 500);
        }
    }

    public function pedidosPorProveedor($id)
    {
        $pedidos = Pedido::whereHas('detalles_pedido', function ($query) use ($id) {
                $query->whereHas('producto', function ($subQuery) use ($id) {
                    $subQuery->where('proveedor_id', $id);
                })->where('notificado_proveedor', 0);
            })
            ->with([
                'detalles_pedido' => function ($query) use ($id) {
                    $query->whereHas('producto', function ($subQuery) use ($id) {
                        $subQuery->where('proveedor_id', $id);
                    })->where('notificado_proveedor', 0);
                },
                'detalles_pedido.producto:id,nombre,precio,stock,tipo',
                'user:id,name'
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($pedidos);
    }

    public function notificarRecolector(Request $request, $pedido_id)
    {
        $pedido = Pedido::find($pedido_id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        $detalle = DetallesPedido::where('pedido_id', $pedido_id)
            ->where('producto_id', $request->producto_id)
            ->first();

        if (!$detalle) {
            return response()->json(['message' => 'Detalle de pedido no encontrado'], 404);
        }

        $detalle->update([
            'notificado_proveedor' => 1
        ]);

        $pedido->update(['estado' => 2]);

        $mensaje = "📦 Pedido #$pedido->id tiene productos listos para recoger.";
        $numeroRecolector = "51948245328";
        $linkWhatsapp = "https://wa.me/$numeroRecolector?text=" . urlencode($mensaje);

        return response()->json([
            'message'           => 'Producto notificado como listo para el recolector',
            'mensaje_plataforma'=> $mensaje,
            'link_whatsapp'     => $linkWhatsapp
        ]);
    }
}
