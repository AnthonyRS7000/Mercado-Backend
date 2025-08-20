<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\DetallesPedido; // 👈 corregido
use App\Models\Personal_Sistema;
use App\Models\Delivery;
use App\Models\Cliente;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PedidoController extends Controller
{
    public function store(Request $request)
    {
        // 1) Validación
        $validator = Validator::make($request->all(), [
            'fecha'               => 'required|date',
            'estado'              => 'required|integer',
            'direccion_entrega'   => 'required|string|max:255',
            'user_id'             => 'required|exists:users,id',
            'metodo_pago_id'      => 'required|exists:metodo_pagos,id',
            'fecha_programada'    => 'nullable|date|after_or_equal:today',
            'hora_programada'     => 'nullable|date_format:H:i',
            'productos'           => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.cantidad'    => 'required|numeric|min:0.1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2) Crear pedido base
        $pedido = Pedido::create([
            'fecha'             => $request->fecha,
            'estado'            => $request->estado,
            'direccion_entrega' => $request->direccion_entrega,
            'user_id'           => $request->user_id,
            'metodo_pago_id'    => $request->metodo_pago_id,
            'fecha_programada'  => $request->fecha_programada,
            'hora_programada'   => $request->hora_programada,
            'total'             => 0,
        ]);

        // 3) Insertar detalles y acumular total
        $total = 0;
        foreach ($request->productos as $p) {
            $prod = Producto::findOrFail($p['producto_id']);
            $subtotal = $prod->precio * $p['cantidad'];

            DetallesPedido::create([ // 👈 corregido
                'pedido_id'       => $pedido->id,
                'producto_id'     => $prod->id,
                'cantidad'        => $p['cantidad'],
                'precio_unitario' => $prod->precio,
                'subtotal'        => $subtotal,
            ]);

            $total += $subtotal;
        }

        // 4) Guardar total y refrescar instancia
        $pedido->total = $total;
        $pedido->save();
        $pedido->refresh();

        // 5) Responder con el pedido ya cargado de nuevo
        return response()->json(
            $pedido->load('detalles_pedido.producto'),
            201
        );
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
        $pedidos = Pedido::where('estado', 2)
                         ->with('detalles_pedido.producto')
                         ->get();

        return $pedidos->isEmpty()
            ? response()->json(['message' => 'No se encontraron pedidos pendientes.'], 404)
            : response()->json($pedidos, 200);
    }

    public function getPedidosListosParaEnviar()
    {
        $pedidos = Pedido::where('estado', 3)
                         ->with(['detalles_pedido.producto', 'user'])
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
        $pedidos = Pedido::with([
            'user:id,name',
            'detalles_pedido.producto',
        ])
        ->where('estado', 10)
        ->get();

        foreach ($pedidos as $pedido) {
            $personal = Personal_Sistema::where('user_id', $pedido->user_id)->first();
            if ($personal) {
                $pedido->comprador = [
                    'nombre' => $personal->nombre,
                    'user_id' => $personal->user_id,
                    'celular' => $personal->celular,
                ];
            } else {
                $delivery = Delivery::where('user_id', $pedido->user_id)->first();
                if ($delivery) {
                    $pedido->comprador = [
                        'nombre' => $delivery->nombre,
                        'user_id' => $delivery->user_id,
                        'celular' => $delivery->celular,
                    ];
                } else {
                    $cliente = Cliente::where('user_id', $pedido->user_id)->first();
                    if ($cliente) {
                        $pedido->comprador = [
                            'nombre' => $cliente->nombre,
                            'user_id' => $cliente->user_id,
                            'celular' => $cliente->celular,
                        ];
                    } else {
                        $proveedor = Proveedor::where('user_id', $pedido->user_id)->first();
                        if ($proveedor) {
                            $pedido->comprador = [
                                'nombre' => $proveedor->nombre,
                                'user_id' => $proveedor->user_id,
                                'celular' => $proveedor->celular,
                            ];
                        } else {
                            $pedido->comprador = null;
                        }
                    }
                }
            }
        }

        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'No se encontraron pedidos pendientes.'], 404);
        }

        $response = $pedidos->map(function ($pedido) {
            return [
                'id' => $pedido->id,
                'fecha' => $pedido->fecha,
                'estado' => $pedido->estado,
                'direccion_entrega' => $pedido->direccion_entrega,
                'total' => $pedido->total,
                'user' => $pedido->user,
                'comprador' => $pedido->comprador,
                'detalles_pedido' => $pedido->detalles_pedido,
            ];
        });

        return response()->json($response, 200);
    }

    public function aceptarPedidoDelivery(Request $request, $pedidoId)
    {
        $request->validate([
            'delivery_id' => 'required|exists:deliveries,id',
        ]);

        $pedido = Pedido::find($pedidoId);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        DB::table('historial_intentos_delivery')->insert([
            'pedido_id' => $pedidoId,
            'delivery_id' => $request->delivery_id,
            'estado' => 'aceptado',
            'created_at' => now(),
        ]);

        $pedido->estado = 20;
        $pedido->delivery_id = $request->delivery_id;
        $pedido->save();

        return response()->json([
            'message' => 'Pedido aceptado por delivery',
            'pedido' => $pedido,
            'redirect_to' => url("/admin/pedido/{$pedidoId}")
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

        DB::table('historial_intentos_delivery')->insert([
            'pedido_id' => $pedidoId,
            'delivery_id' => $request->delivery_id,
            'estado' => 'cancelado',
            'created_at' => now(),
        ]);

        $pedido->estado = 10;
        $pedido->delivery_id = null;
        $pedido->save();

        return response()->json(['message' => 'Pedido cancelado y vuelto a estado disponible']);
    }

    public function getPedidoById(Request $request, $pedidoId, $deliveryId)
    {
        $pedido = Pedido::with([
            'user',
            'detalles_pedido.producto'
        ])->find($pedidoId);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        if ($pedido->delivery_id !== (int)$deliveryId) {
            return response()->json(['message' => 'Este pedido no está asignado a este delivery.'], 403);
        }

        $user = $pedido->user;

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

        return response()->json([
            'pedido' => $pedido,
            'user_data' => $userData,
            'tipo_usuario' => $tipoUsuario
        ], 200);
    }

    public function getPedidoActivo($deliveryId)
    {
        $pedido = Pedido::where('delivery_id', $deliveryId)
                        ->whereIn('estado', [4, 20, 30])
                        ->first();

        if ($pedido) {
            return response()->json(['pedido_id' => $pedido->id]);
        } else {
            return response()->json(['pedido_id' => null]);
        }
    }
}
