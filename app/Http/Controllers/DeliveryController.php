<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\User;
use App\Models\Role;
use App\Models\Pedido; // Asegúrate de importar el modelo Pedido
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class DeliveryController extends Controller
{
    public function index()
    {
        $deliveries = Delivery::all();
        return response()->json($deliveries, 200);
    }

    public function show($id)
    {
        $delivery = Delivery::find($id);

        if (!$delivery) {
            return response()->json(['error' => 'Delivery no encontrado.'], 404);
        }

        return response()->json($delivery, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'nombre_empresa' => 'required|string|max:255',
            'dni' => 'required|string|max:255|unique:deliveries,dni',
            'celular' => 'required|string|max:255|unique:deliveries,celular',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Obtener el objeto Role con num_rol = X (cambiar X al número correspondiente)
        $role = Role::where('num_rol', 4)->first(); // Reemplazar X por el número correspondiente

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

        // Crear delivery asociado al usuario
        $delivery = Delivery::create([
            'nombre' => $request->nombre,
            'nombre_empresa' => $request->nombre_empresa,
            'dni' => $request->dni,
            'celular' => $request->celular,
            'user_id' => $user->id,
        ]);

        return response()->json(['user' => $user, 'delivery' => $delivery], 201);
    }

    public function update(Request $request, $id)
    {
        $delivery = Delivery::find($id);

        if (!$delivery) {
            return response()->json(['error' => 'Delivery no encontrado.'], 404);
        }

        $validatedData = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'nombre_empresa' => 'sometimes|required|string|max:255',
            'dni' => 'sometimes|required|string|max:255|unique:deliveries,dni,' . $id,
            'celular' => 'sometimes|required|string|max:255|unique:deliveries,celular,' . $id,
            'user_id' => 'sometimes|required|exists:users,id',
        ]);

        $delivery->update($validatedData);

        return response()->json($delivery, 200);
    }

    public function destroy($id)
    {
        $delivery = Delivery::find($id);

        if (!$delivery) {
            return response()->json(['error' => 'Delivery no encontrado.'], 404);
        }

        $delivery->delete();

        return response()->json(['message' => 'Delivery eliminado exitosamente.'], 200);
    }


    public function updatePedidoEstado(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'delivery_id' => 'required|integer|exists:deliveries,id',
            'pedido_id' => 'required|integer|exists:pedidos,id',
            'estado' => 'required|integer', // Validación para estado como entero
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pedido = Pedido::find($request->pedido_id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        // Aquí puedes realizar cualquier otra lógica de validación o procesamiento

        $pedido->estado = $request->estado;
        $pedido->save();

        return response()->json(['message' => 'Estado del pedido actualizado exitosamente.', 'pedido' => $pedido], 200);
    }
}
