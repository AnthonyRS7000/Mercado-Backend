<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json(['error' => 'Credenciales inválidas.'], 401);
        }

        // Obtener el usuario autenticado
        $user = Auth::user();

        // Verificar si el usuario tiene un rol asignado
        if (!$user->role) {
            return response()->json(['error' => 'El usuario no tiene un rol asignado.'], 401);
        }

        // Obtener el num_rol del rol asociado al usuario
        $numRol = $user->role->num_rol;

        // Determinar el tipo de usuario y los datos relacionados
        $userType = null;
        $relatedData = null;

        // Determinar el tipo de usuario y datos relacionados según la relación existente
        if ($user->cliente) {
            $userType = 'Cliente';
            $relatedData = $user->cliente;
        } elseif ($user->delivery) {
            $userType = 'Delivery';
            $relatedData = $user->delivery;
        } elseif ($user->proveedor) {
            $userType = 'Proveedor';
            $relatedData = $user->proveedor;
        } elseif ($user->personalSistema) {
            $userType = 'Personal_sistema';
            $relatedData = $user->personalSistema;
        }

        return response()->json([
            'access_token' => $user->createToken('auth_token')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'num_rol' => $numRol,
                'user_type' => $userType,
                'related_data' => $relatedData,
            ],
        ], 200);
    }
}
