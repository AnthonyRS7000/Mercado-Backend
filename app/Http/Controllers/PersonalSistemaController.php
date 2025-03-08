<?php

namespace App\Http\Controllers;

use App\Models\Personal_Sistema;
use App\Models\User;
use App\Models\Role;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PersonalSistemaController extends Controller
{
    public function index()
    {
        $personalSistemas = Personal_Sistema::all();
        return response()->json($personalSistemas, 200);
    }

    public function show($id)
    {
        $personalSistema = Personal_Sistema::find($id);

        if (!$personalSistema) {
            return response()->json(['error' => 'Personal de sistemas no encontrado.'], 404);
        }

        return response()->json($personalSistema, 200);
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
        $pedidos = Pedido::where('estado', 'notificado')
            ->orderBy('created_at', 'asc')
            ->get();
    
        return response()->json($pedidos, 200);
    }
    
    
    
}
