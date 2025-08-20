<?php

namespace App\Http\Controllers;

use App\Models\Personal_Sistema;
use App\Models\Delivery;
use App\Models\Proveedor;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Role;
use App\Models\Pedido;
use App\Models\DetallesPedido; // 👈 corregido
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PersonalSistemaController extends Controller
{
    public function index()
    {
        $personalSistemas = Personal_Sistema::select('personal_sistemas.*', 'users.email')
            ->join('users', 'personal_sistemas.user_id', '=', 'users.id')
            ->get();

        return response()->json($personalSistemas, 200);
    }

    public function show($id)
    {
        $personalSistema = Personal_Sistema::find($id);

        if (!$personalSistema) {
            return response()->json(['error' => 'Personal de sistemas no encontrado.'], 404);
        }

        return response()->json(['Apoyo' => $personalSistema], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'dni' => 'required|string|max:255|unique:personal_sistemas,dni',
            'celular' => 'required|string|max:255|unique:personal_sistemas,celular',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Rol de personal de sistemas (num_rol = 3)
        $role = Role::where('num_rol', 3)->first();
        if (!$role) {
            return response()->json(['error' => 'El rol no existe.'], 404);
        }

        $user = User::create([
            'name' => $request->nombre,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $role->id,
        ]);

        $personalSistema = Personal_Sistema::create([
            'nombre' => $request->nombre,
            'dni' => $request->dni,
            'celular' => $request->celular,
            'user_id' => $user->id,
        ]);

        return response()->json(['user' => $user, 'personal_sistema' => $personalSistema], 201);
    }

    public function update(Request $request, $id)
    {
        $personalSistema = Personal_Sistema::find($id);
        if (!$personalSistema) {
            return response()->json(['error' => 'Personal de sistemas no encontrado.'], 404);
        }

        $validatedData = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'dni' => 'sometimes|required|string|max:255|unique:personal_sistemas,dni,' . $id,
            'celular' => 'sometimes|required|string|max:255|unique:personal_sistemas,celular,' . $id,
            'user_id' => 'sometimes|required|exists:users,id',
        ]);

        $personalSistema->update($validatedData);

        return response()->json($personalSistema, 200);
    }

    public function destroy($id)
    {
        $personalSistema = Personal_Sistema::find($id);
        if (!$personalSistema) {
            return response()->json(['error' => 'Personal de sistemas no encontrado.'], 404);
        }

        $personalSistema->delete();

        return response()->json(['message' => 'Personal de sistemas eliminado exitosamente.'], 200);
    }

    public function pedidosNotificados()
    {
        $pedidos = DetallesPedido::with([
                'pedido.user:id,name',
                'pedido.cliente:id,user_id,nombre,celular',
                'producto.proveedor:id,nombre,nombre_empresa',
            ])
            ->select('id', 'pedido_id', 'producto_id', 'cantidad', 'subtotal', 'notificado_proveedor', 'created_at')
            ->where('notificado_proveedor', '!=', 2)
            ->get()
            ->groupBy('pedido_id');

        $pedidosFiltrados = $pedidos->filter(function ($detallePedidos) {
            return $detallePedidos->contains(fn($detalle) =>
                $detalle->notificado_proveedor == 0 || $detalle->notificado_proveedor == 1
            );
        });

        return response()->json($pedidosFiltrados, 200);
    }

    public function marcarProductoListo(Request $request, $pedido_id)
    {
        $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'recolector_id' => 'required|exists:personal_sistemas,id'
        ]);

        $detalle = DetallesPedido::where('pedido_id', $pedido_id)
            ->where('producto_id', $request->producto_id)
            ->first();

        if (!$detalle) {
            return response()->json(['message' => 'Detalle de pedido no encontrado'], 404);
        }

        $detalle->update([
            'notificado_proveedor' => 2,
            'personal_sistema_id' => $request->recolector_id
        ]);

        $todosListos = DetallesPedido::where('pedido_id', $pedido_id)
            ->where('notificado_proveedor', '!=', 2)
            ->count() === 0;

        if ($todosListos) {
            $pedido = Pedido::find($pedido_id);
            if ($pedido) {
                $pedido->update(['estado' => 3]);
            }
        }

        return response()->json([
            'message' => 'Estado del producto actualizado a 2',
            'detalle' => $detalle
        ]);
    }

    public function pedidosListosParaRecoger()
    {
        $detalles = DetallesPedido::with([
                'pedido.user:id,name',
                'pedido.cliente:id,user_id,nombre,celular',
                'producto.proveedor:id,nombre,nombre_empresa'
            ])
            ->whereHas('pedido', fn($q) => $q->where('estado', 3))
            ->select('id','pedido_id','producto_id','cantidad','precio_unitario','notificado_proveedor','created_at')
            ->get();

        $agrupados = $detalles->groupBy('pedido_id');
        $listos = $agrupados->filter(fn($productos) =>
            $productos->every(fn($p) => $p->notificado_proveedor == 2)
        );

        if ($listos->isEmpty()) {
            return response()->json(['message' => 'No hay pedidos listos para recoger'], 200);
        }

        return response()->json($listos, 200);
    }

    public function pedidosPorConfirmar()
    {
        $pedidos = Pedido::with([
                'user:id,name',
                'detalles_pedido.producto.proveedor:id,nombre,nombre_empresa',
                'detalles_pedido.producto.categoria:id,nombre',
            ])
            ->where('estado', 20)
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
                        $pedido->comprador = $proveedor ? [
                            'nombre' => $proveedor->nombre,
                            'user_id' => $proveedor->user_id,
                            'celular' => $proveedor->celular,
                        ] : null;
                    }
                }
            }
        }

        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'No hay pedidos pendientes por confirmar'], 200);
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
                'detalles_pedido' => $pedido->detalles_pedido->map(function ($detalle) {
                    return [
                        'id' => $detalle->id,
                        'cantidad' => $detalle->cantidad,
                        'precio_unitario' => $detalle->precio_unitario,
                        'producto_id' => $detalle->producto_id,
                        'nombre_producto' => $detalle->producto->nombre ?? null,
                        'proveedor' => [
                            'id' => $detalle->producto->proveedor->id ?? null,
                            'nombre' => $detalle->producto->proveedor->nombre ?? null,
                            'nombre_empresa' => $detalle->producto->proveedor->nombre_empresa ?? null,
                        ],
                        'categoria' => [
                            'id' => $detalle->producto->categoria->id ?? null,
                            'nombre' => $detalle->producto->categoria->nombre ?? null,
                        ],
                    ];
                })
            ];
        });

        return response()->json($response, 200);
    }

    public function confirmarPedido($id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        if ($pedido->estado !== 20) {
            return response()->json(['message' => 'El pedido no está en estado 20'], 400);
        }

        $pedido->estado = 4;
        $pedido->save();

        return response()->json(['message' => 'Pedido confirmado con éxito'], 200);
    }
}
