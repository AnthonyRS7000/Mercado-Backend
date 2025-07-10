<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\User;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Carrito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ClienteController extends Controller
{
    public function index()
    {
        $clientes = Cliente::all();
        return response()->json($clientes, 200);
    }

    public function show($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado.'], 404);
        }

        return response()->json($cliente, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'dni' => 'required|string|max:255|unique:clientes,dni',
            'celular' => 'required|string|max:255|unique:clientes,celular',
            'direccion' => 'required|string|max:255',
            'preferencias_compra' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = Role::where('num_rol', 1)->first();
        if (!$role) {
            return response()->json(['error' => 'El rol no existe.'], 404);
        }

        // Crear usuario
        $user = User::create([
            'name' => $request->nombre,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $role->id,
        ]);

        // Crear cliente asociado
        $cliente = Cliente::create([
            'nombre' => $request->nombre,
            'dni' => $request->dni,
            'celular' => $request->celular,
            'direccion' => $request->direccion,
            'preferencias_compra' => $request->preferencias_compra,
            'user_id' => $user->id,
        ]);

        // Crear carrito vacío usando el user_id
        $carrito = Carrito::create([
            'user_id' => $user->id,
        ]);

        return response()->json([
            'user' => $user,
            'cliente' => $cliente,
            'carrito' => $carrito
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado.'], 404);
        }

        $validatedData = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'dni' => 'sometimes|required|string|max:255|unique:clientes,dni,' . $id,
            'celular' => 'sometimes|required|string|max:255|unique:clientes,celular,' . $id,
            'direccion' => 'sometimes|required|string|max:255',
            'preferencias_compra' => 'nullable|string|max:255',
            'user_id' => 'sometimes|required|exists:users,id',
        ]);

        $cliente->update($validatedData);

        return response()->json($cliente, 200);
    }

    public function destroy($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado.'], 404);
        }

        $cliente->delete();

        return response()->json(['message' => 'Cliente eliminado exitosamente.'], 200);
    }

    public function getPedidosByUserId($user_id)
    {
        $pedidos = Pedido::where('user_id', $user_id)->get();

        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'No se encontraron pedidos para este usuario.'], 404);
        }

        return response()->json($pedidos, 200);
    }
}
