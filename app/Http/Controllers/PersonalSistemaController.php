<?php

namespace App\Http\Controllers;

use App\Models\Personal_Sistema;
use App\Models\Delivery;
use App\Models\Proveedor;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Role;
use App\Models\Pedido;
use App\Models\detalles_pedido;
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

        return response()->json(['user' => $user, 'Apoyo' => $personalSistema], 201);
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

        // Obtener el objeto Role con num_rol = 2 (personal de sistemas)
        $role = Role::where('num_rol', 3)->first();

        if (!$role) {
            return response()->json(['error' => 'El rol no existe.'], 404);
        }

        // Crear usuario con el rol correspondiente
        $user = User::create([
            'name' => $request->nombre,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $role->id,
        ]);

        // Crear personal de sistemas asociado al usuario
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
        // Obtenemos los pedidos con detalles y el estado de notificación
        $pedidos = Detalles_Pedido::with([
                'pedido.user:id,name',
                'pedido.cliente:id,user_id,nombre,celular',
                'producto.proveedor:id,nombre,nombre_empresa', // Relación corregida
            ])
            ->select('id', 'pedido_id', 'producto_id', 'cantidad', 'subtotal', 'notificado_proveedor', 'created_at')
            ->where('notificado_proveedor', '!=', 2) // Excluimos los productos con notificado_proveedor == 2
            ->get()
            ->groupBy('pedido_id'); // Agrupamos por pedido

        // Filtrar solo aquellos pedidos que tengan productos que no estén marcados como 'notificado_proveedor == 2'
        $pedidosFiltrados = $pedidos->filter(function($detallePedidos) {
            // Verifica si hay al menos un producto con notificado_proveedor == 0 o 1
            return $detallePedidos->contains(function($detalle) {
                return $detalle->notificado_proveedor == 0 || $detalle->notificado_proveedor == 1;
            });
        });

        // Retornamos los pedidos y sus productos filtrados
        return response()->json($pedidosFiltrados, 200);
    }


    public function marcarProductoListo(Request $request, $pedido_id)
    {
        $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'recolector_id' => 'required|exists:personal_sistemas,id'
        ]);

        // Obtener el detalle del pedido que se está actualizando
        $detalle = Detalles_Pedido::where('pedido_id', $pedido_id)
            ->where('producto_id', $request->producto_id)
            ->first();

        if (!$detalle) {
            return response()->json(['message' => 'Detalle de pedido no encontrado'], 404);
        }

        // Actualizar el estado del producto
        $detalle->update([
            'notificado_proveedor' => 2,
            'personal_sistema_id' => $request->recolector_id
        ]);

        // Verificar si todos los productos del pedido tienen 'notificado_proveedor' == 2
        $todosListos = Detalles_Pedido::where('pedido_id', $pedido_id)
            ->where('notificado_proveedor', '!=', 2) // Buscar productos que no estén en estado 2
            ->count() === 0; // Si no hay productos con estado distinto de 2, todos están listos

        if ($todosListos) {
            // Si todos los productos están listos, actualizar el estado del pedido a 3
            $pedido = Pedido::find($pedido_id);
            if ($pedido) {
                $pedido->update(['estado' => 3]); // Actualiza el estado del pedido
            }
        }

        return response()->json([
            'message' => 'Estado del producto actualizado a 2',
            'detalle' => $detalle
        ]);
    }



public function pedidosListosParaRecoger()
{
    // 1) Traer todos los detalles de pedido cuyos pedidos estén en estado 3
    $detalles = Detalles_Pedido::with([
            'pedido.user:id,name',
            'pedido.cliente:id,user_id,nombre,celular',
            'producto.proveedor:id,nombre,nombre_empresa'
        ])
        // Validación: solo detalles de pedidos con estado = 3
        ->whereHas('pedido', function($q) {
            $q->where('estado', 3);
        })
        ->select(
            'id',
            'pedido_id',
            'producto_id',
            'cantidad',
            'precio_unitario',
            'notificado_proveedor',
            'created_at'
        )
        ->get();

    // 2) Agrupar por pedido_id
    $agrupados = $detalles->groupBy('pedido_id');

    // 3) Filtrar sólo los grupos donde TODOS los productos tienen notificado_proveedor == 2
    $listos = $agrupados->filter(function ($productos) {
        return $productos->every(fn($p) => $p->notificado_proveedor == 2);
    });

    // 4) Si no hay ninguno, retornamos mensaje
    if ($listos->isEmpty()) {
        return response()->json(['message' => 'No hay pedidos listos para recoger'], 200);
    }

    // 5) Devolvemos el objeto agrupado
    return response()->json($listos, 200);
}

    public function pedidosPorConfirmar()
    {
        // Realizamos la consulta buscando pedidos con estado 20
        $pedidos = Pedido::with([
            'user:id,name', // Relación con la tabla de usuarios (clientes)
            'detalles_Pedido.producto.proveedor:id,nombre,nombre_empresa', // Relación con productos y proveedores
            'detalles_Pedido.producto.categoria:id,nombre', // Si necesitas la categoría del producto
        ])
        ->where('estado', 20)
        ->get();

        // Iteramos sobre los pedidos para asignar el comprador
        foreach ($pedidos as $pedido) {
            // Verificar en la tabla de personal_sistemas
            $personal = Personal_Sistema::where('user_id', $pedido->user_id)->first();
            if ($personal) {
                $pedido->comprador = [
                    'nombre' => $personal->nombre,
                    'user_id' => $personal->user_id,
                    'celular' => $personal->celular,
                ];
            } else {
                // Verificar en la tabla de deliveries
                $delivery = Delivery::where('user_id', $pedido->user_id)->first();
                if ($delivery) {
                    $pedido->comprador = [
                        'nombre' => $delivery->nombre,
                        'user_id' => $delivery->user_id,
                        'celular' => $delivery->celular,
                    ];
                } else {
                    // Verificar en la tabla de clientes
                    $cliente = Cliente::where('user_id', $pedido->user_id)->first();
                    if ($cliente) {
                        $pedido->comprador = [
                            'nombre' => $cliente->nombre,
                            'user_id' => $cliente->user_id,
                            'celular' => $cliente->celular,
                        ];
                    } else {
                        // Verificar en la tabla de proveedores
                        $proveedor = Proveedor::where('user_id', $pedido->user_id)->first();
                        if ($proveedor) {
                            $pedido->comprador = [
                                'nombre' => $proveedor->nombre,
                                'user_id' => $proveedor->user_id,
                                'celular' => $proveedor->celular,
                            ];
                        } else {
                            // Si no se encuentra ningún comprador, asignar null
                            $pedido->comprador = null;
                        }
                    }
                }
            }
        }

        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'No hay pedidos pendientes por confirmar'], 200);
        }

        // Estructuramos el JSON de respuesta
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

        $pedido->estado = 4; // Nuevo estado
        $pedido->save();

        return response()->json(['message' => 'Pedido confirmado con éxito'], 200);
    }
}
