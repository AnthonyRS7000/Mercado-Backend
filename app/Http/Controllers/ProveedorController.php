<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProveedorController extends Controller
{
    public function index()
    {
        $Proveedors = Proveedor::select('proveedors.*', 'users.email')  // Selecciona los campos de Proveedor y el email del usuario
            ->join('users', 'proveedors.user_id', '=', 'users.id')  // Realiza un JOIN entre proveedors y users
            ->with('categorias')  // Incluye las categorías del proveedor
            ->get();

        return response()->json($Proveedors, 200);
    }


    public function show($id)
    {
        $Proveedor = Proveedor::with('categorias')->find($id);

        if (!$Proveedor) {
            return response()->json(['error' => 'Proveedor no encontrado.'], 404);
        }

        return response()->json(['user' => $user, 'Proveedor' => $Proveedor], 201);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'nombre_empresa' => 'required|string|max:255',
            'dni' => 'required|string|max:255|unique:proveedors,dni',
            'celular' => 'required|string|max:255|unique:proveedors,celular',
            'direccion' => 'required|string|max:255',
            'ids' => 'required|array',
            'ids.*' => 'exists:categorias,id',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Obtener el objeto Role con num_rol = 1
        $role = Role::where('num_rol', 2)->first();

        if (!$role) {
            return response()->json(['error' => 'El rol no existe.'], 404);
        }

        // Crear usuario con num_rol = 1 por defecto
        $user = User::create([
            'name' => $request->nombre,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $role->id, // Asignar el id del rol correspondiente al valor de num_rol
        ]);

        // Crear Proveedor asociado
        $Proveedor = Proveedor::create([
            'nombre' => $request->nombre,
            'nombre_empresa'=> $request->nombre_empresa,
            'dni' => $request->dni,
            'celular' => $request->celular,
            'direccion' => $request->direccion,
            'user_id' => $user->id,
        ]);

        // Asignar categorías
        $Proveedor->categorias()->sync($request->ids);

        return response()->json(['user' => $user, 'Proveedor' => $Proveedor], 201);
    }

    public function update(Request $request, $id)
    {
        $Proveedor = Proveedor::find($id);

        if (!$Proveedor) {
            return response()->json(['error' => 'Proveedor no encontrado.'], 404);
        }

        $validatedData = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'nombre_empresa' => 'required|string|max:255',
            'dni' => 'sometimes|required|string|max:255|unique:proveedors,dni,' . $id,
            'celular' => 'sometimes|required|string|max:255|unique:proveedors,celular,' . $id,
            'direccion' => 'sometimes|required|string|max:255',
            'ids' => 'sometimes|required|array',
            'ids.*' => 'exists:categorias,id',
            'user_id' => 'sometimes|required|exists:users,id',
        ]);

        $Proveedor->update($validatedData);

        if ($request->has('ids')) {
            $Proveedor->categorias()->sync($request->ids);
        }

        return response()->json($Proveedor, 200);
    }

    public function destroy($id)
    {
        $Proveedor = Proveedor::find($id);

        if (!$Proveedor) {
            return response()->json(['error' => 'Proveedor no encontrado.'], 404);
        }

        $Proveedor->delete();

        return response()->json(['message' => 'Proveedor eliminado exitosamente.'], 200);
    }

    public function proveedorPorId($id)
    {
        $proveedor = Proveedor::with('categorias')->find($id);

        if (!$proveedor) {
            return response()->json(['error' => 'Proveedor no encontrado.'], 404);
        }

        return response()->json($proveedor, 200);
    }

    public function pedidosPorProveedor($id)
    {
        $pedidos = Pedido::whereHas('detalles_pedido.producto', function ($query) use ($id) {
                $query->where('proveedor_id', $id);
            })
            ->with([
                'detalles_pedido' => function ($query) use ($id) {
                    $query->whereHas('producto', function ($subQuery) use ($id) {
                        $subQuery->where('proveedor_id', $id);
                    });
                },
                'detalles_pedido.producto',
                'user:id,name'
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($pedidos);
    }
    public function categoriasPorProveedor($id)
    {
        $proveedor = Proveedor::find($id);

        if (!$proveedor) {
            return response()->json(['error' => 'Proveedor no encontrado.'], 404);
        }

        $categorias = $proveedor->categorias; // relación many-to-many

        return response()->json($categorias, 200);
    }

}
