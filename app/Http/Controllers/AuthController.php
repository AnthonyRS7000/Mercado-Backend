<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\SolicitudRegistro;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:users,email',
            'password'  => 'required|string|min:8',
            'role_id'   => 'nullable|exists:roles,id',
            'num_rol'   => 'nullable|integer|exists:roles,num_rol',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!$request->filled('role_id') && !$request->filled('num_rol')) {
            return response()->json(['error' => 'Debes enviar role_id o num_rol.'], 422);
        }

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
            'password' => Hash::make($request->password),
            'role_id'  => $roleId,
        ]);

        return response()->json([
            'message' => 'Usuario creado',
            'user'    => $user,
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

        $email = $request->input('email');
        $credentials = $request->only('email', 'password');

        $solicitud = SolicitudRegistro::where('email', $email)
            ->orderByDesc('created_at')
            ->first();

        $userByEmail = User::where('email', $email)->first();
        if (!$userByEmail && $solicitud) {
            if ($solicitud->estado === 'rechazada') {
                return response()->json(['error' => 'Su solicitud fue RECHAZADA. Por favor comuníquese con el administrador.'], 403);
            }
            if ($solicitud->estado === 'pendiente') {
                return response()->json(['error' => 'Su solicitud está PENDIENTE de aprobación. Intente más tarde.'], 403);
            }
        }

        if (!Auth::attempt($credentials)) {
            if ($solicitud) {
                if ($solicitud->estado === 'rechazada') {
                    return response()->json(['error' => 'Su solicitud fue RECHAZADA. Por favor comuníquese con el administrador.'], 403);
                }
                if ($solicitud->estado === 'pendiente') {
                    return response()->json(['error' => 'Su solicitud está PENDIENTE de aprobación. Intente más tarde.'], 403);
                }
            }
            return response()->json(['error' => 'Credenciales inválidas.'], 401);
        }

        $user = Auth::user();

        if ($solicitud && in_array($solicitud->estado, ['pendiente', 'rechazada'])) {
            Auth::logout();
            return response()->json([
                'error' => $solicitud->estado === 'rechazada'
                    ? 'Su solicitud fue RECHAZADA. Por favor comuníquese con el administrador.'
                    : 'Su solicitud está PENDIENTE de aprobación. Intente más tarde.'
            ], 403);
        }

        if (!$user->role) {
            Auth::logout();
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
            $userType = 'PersonalSistema';
            $relatedData = $user->personalSistema;
        }

        $uuid = $request->input('carrito_uuid');
        if ($uuid) {
            \DB::table('carritos')
                ->where('uuid', $uuid)
                ->update(['user_id' => $user->id]);
        }

        $carrito = \DB::table('carritos')
            ->where('user_id', $user->id)
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
            ->where('user_id', $request->user()->id)
            ->update(['uuid' => null]);

        return response()->json(['message' => 'Successfully logged out']);
    }
}
