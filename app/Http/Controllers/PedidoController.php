<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\detalles_pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PedidoController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'estado' => 'required|integer',
            'direccion_entrega' => 'required|string|max:255',
            'user_id' => 'required|exists:users,id',
            'metodo_pago_id' => 'required|exists:metodo_pagos,id',
            'fecha_programada' => 'nullable|date|after_or_equal:today',
            'hora_programada' => 'nullable|date_format:H:i',
            'productos' => 'required|array',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.cantidad' => 'required|numeric|min:0.1'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pedido = Pedido::create([
            'fecha' => $request->fecha,
            'estado' => $request->estado,
            'direccion_entrega' => $request->direccion_entrega,
            'user_id' => $request->user_id,
            'metodo_pago_id' => $request->metodo_pago_id,
            'fecha_programada' => $request->fecha_programada,
            'hora_programada' => $request->hora_programada,
            'total' => 0
        ]);

        $total = 0;
        foreach ($request->productos as $producto) {
            $productoModel = Producto::find($producto['producto_id']);
            $precioUnitario = $productoModel->precio;
            $cantidad = $producto['cantidad'];
            $subtotal = $precioUnitario * $cantidad;

            detalles_pedido::create([
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal' => $subtotal,
                'pedido_id' => $pedido->id,
                'producto_id' => $producto['producto_id']
            ]);

            $total += $subtotal;
        }

        $pedido->total = $total;
        $pedido->save();

        return response()->json($pedido->load('detalles_pedido.producto'), 201);
    }


    public function getLastPedido($userId)
    {
        $pedido = Pedido::where('user_id', $userId)
                        ->with('detalles_pedido.producto')
                        ->latest()
                        ->first();

        if (!$pedido) {
            return response()->json(['message' => 'No se encontró ningún pedido.'], 404);
        }

        return response()->json($pedido, 200);
    }

public function getPedidosByUserId($userId)
{
    $pedidos = Pedido::where('user_id', $userId)
                     ->with('detalles_pedido.producto')
                     ->orderBy('created_at', 'desc')
                     ->get();

    if ($pedidos->isEmpty()) {
        return response()->json(['message' => 'No se encontraron pedidos para este usuario.'], 404);
    }

    return response()->json($pedidos, 200);
}


    public function getPedidosPendientes()
    {
        $pedidos = Pedido::where('estado', 2)->with('detalles_pedido.producto')->get();
        \Log::info('Pedidos con estado 2:', $pedidos->toArray());

        return $pedidos->isEmpty()
            ? response()->json(['message' => 'No se encontraron pedidos pendientes.'], 404)
            : response()->json($pedidos, 200);
    }

    public function getPedidosListosParaEnviar()
    {
        $pedidos = Pedido::where('estado', 3)
                        ->with(['detalles_pedido.producto', 'user']) // Incluye también al usuario si lo necesitas
                        ->orderBy('created_at', 'desc')
                        ->get();

        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'No se encontraron pedidos con estado 3 (listos para recoger).'], 404);
        }

        return response()->json($pedidos, 200);
    }

    public function LLamarDelivery(Request $request, $pedidoId)
    {
        $request->validate([
            'personal_sistema_id' => 'required|exists:personal_sistemas,id',
        ]);

        $pedido = Pedido::find($pedidoId);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        $pedido->estado = 10;
        $pedido->personal_sistema_id = $request->personal_sistema_id;
        $pedido->save();

        return response()->json([
            'message' => 'Pedido actualizado correctamente.',
            'pedido' => $pedido
        ], 200);
    }

    public function pedidosParaRecoger()
    {
        $pedidos = Pedido::where('estado', 10)->with('detalles_pedido.producto')->get();
        \Log::info('Pedidos con estado 10:', $pedidos->toArray());

        return $pedidos->isEmpty()
            ? response()->json(['message' => 'No se encontraron pedidos pendientes.'], 404)
            : response()->json($pedidos, 200);
    }

    public function aceptarPedidoDelivery(Request $request, $pedidoId)
    {
        // Validar que el delivery_id esté presente y sea válido
        $request->validate([
            'delivery_id' => 'required|exists:deliveries,id',
        ]);

        // Buscar el pedido por ID
        $pedido = Pedido::find($pedidoId);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        // Registrar intento en el historial
        DB::table('historial_intentos_delivery')->insert([
            'pedido_id' => $pedidoId,
            'delivery_id' => $request->delivery_id,
            'estado' => 'aceptado',
            'created_at' => now(),
        ]);

        // Asignar el delivery y actualizar el estado del pedido
        $pedido->estado = 20; // Cambiar el estado a "aceptado"
        $pedido->delivery_id = $request->delivery_id; // Asignar el delivery
        $pedido->save();

        // Redirigir a la vista del pedido específico, o devolver la información en la respuesta
        return response()->json([
            'message' => 'Pedido aceptado por delivery',
            'pedido' => $pedido, // Retorna los detalles del pedido actualizado
            'redirect_to' => url("/admin/pedido/{$pedidoId}") // Incluir la URL del pedido para redirigir
        ]);
    }


    public function actualizarEstadoEnRuta(Request $request, $pedidoId)
    {
        $request->validate([
            'estado' => 'required|in:20,30',
        ]);

        $pedido = Pedido::find($pedidoId);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        $pedido->estado = $request->estado;
        $pedido->save();

        return response()->json(['message' => 'Estado actualizado', 'estado' => $request->estado]);
    }

    public function cancelarPedidoDelivery(Request $request, $pedidoId)
    {
        $request->validate([
            'delivery_id' => 'required|exists:deliveries,id',
        ]);

        $pedido = Pedido::find($pedidoId);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        // Registrar cancelación
        DB::table('historial_intentos_delivery')->insert([
            'pedido_id' => $pedidoId,
            'delivery_id' => $request->delivery_id,
            'estado' => 'cancelado',
            'created_at' => now(),
        ]);

        $pedido->estado = 10; // Vuelve a estar disponible
        $pedido->delivery_id = null;
        $pedido->save();

        return response()->json(['message' => 'Pedido cancelado y vuelto a estado disponible']);
    }

    public function getPedidoById(Request $request, $pedidoId, $deliveryId)
    {
        // Buscar el pedido
        $pedido = Pedido::with([
            'user', // Cargar siempre el usuario
            'detalles_pedido.producto'
        ])->find($pedidoId);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        // Verificar si el pedido pertenece al delivery
        if ($pedido->delivery_id !== (int)$deliveryId) {
            return response()->json(['message' => 'Este pedido no está asignado a este delivery.'], 403);
        }

        // Cargar dinámicamente los datos dependiendo del rol
        $user = $pedido->user;

        // Puedes agregar una columna 'rol' en users, o verificar con relaciones existentes
        if ($user->cliente) {
            $userData = $user->cliente;
            $tipoUsuario = 'cliente';
        } elseif ($user->proveedor) {
            $userData = $user->proveedor;
            $tipoUsuario = 'proveedor';
        } elseif ($user->delivery) {
            $userData = $user->delivery;
            $tipoUsuario = 'delivery';
        } else {
            $userData = null;
            $tipoUsuario = 'desconocido';
        }

        // Preparar la respuesta
        return response()->json([
            'pedido' => $pedido,
            'user_data' => $userData,
            'tipo_usuario' => $tipoUsuario
        ], 200);
    }


    public function getPedidoActivo($deliveryId)
    {
        $pedido = Pedido::where('delivery_id', $deliveryId)
                        ->whereIn('estado', [4, 20, 30]) // o los estados que signifiquen "en curso"
                        ->first();

        if ($pedido) {
            return response()->json(['pedido_id' => $pedido->id]);
        } else {
            return response()->json(['pedido_id' => null]);
        }
    }
}
