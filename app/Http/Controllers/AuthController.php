<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:users,email',
            'password'  => 'required|string|min:8',
            // envía UNO de los dos:
            'role_id'   => 'nullable|exists:roles,id',
            'num_rol'   => 'nullable|integer|exists:roles,num_rol',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!$request->filled('role_id') && !$request->filled('num_rol')) {
            return response()->json(['error' => 'Debes enviar role_id o num_rol.'], 422);
        }

        // Resolver el ID real del rol
        $roleId = $request->input('role_id');
        if (!$roleId) {
            $roleId = Role::where('num_rol', $request->input('num_rol'))->value('id');
            if (!$roleId) {
                return response()->json(['error' => 'El rol no existe.'], 404);
            }
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password), // bcrypt
            'role_id'  => $roleId,
        ]);

        // Si quieres devolver token de una vez (Sanctum):
        // $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario creado',
            'user'    => $user,
            // 'access_token' => $token,
            // 'token_type'   => 'Bearer',
        ], 201);
    }
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

        $user = Auth::user();

        if (!$user->role) {
            return response()->json(['error' => 'El usuario no tiene un rol asignado.'], 401);
        }

        $numRol = $user->role->num_rol;

        $userType = null;
        $relatedData = null;

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

        // Actualizar carrito para el cliente
        $uuid = $request->input('carrito_uuid');
        if ($uuid) {
            \DB::table('carritos')
                ->where('uuid', $uuid)
                ->update(['user_id' => $user->id]); // Cambiado a user_id
        }

        // Obtener el uuid del carrito del cliente si existe
        $carrito = \DB::table('carritos')
            ->where('user_id', $user->id) // Cambiado a user_id
            ->first();

        return response()->json([
            'access_token' => $user->createToken('auth_token')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'num_rol' => $numRol,
                'user_type' => $userType,
                'related_data' => $relatedData,
                'carrito_uuid' => $carrito ? $carrito->uuid : null,
            ],
        ], 200);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        \DB::table('carritos')
            ->where('user_id', $request->user()->id) // Cambiado a user_id
            ->update(['uuid' => null]);

        return response()->json(['message' => 'Successfully logged out']);
    }
}
