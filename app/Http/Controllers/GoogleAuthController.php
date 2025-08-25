<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\Role;
use App\Models\Cliente;
use App\Models\Carrito;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    public function handleGoogleCallback()
    {
        try {
            Log::info('Recibiendo respuesta de Google...');

            $googleUser = Socialite::driver('google')->user();
            Log::info('Usuario autenticado con Google: ' . $googleUser->getEmail());

            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                Log::info('El usuario no existe, creando nuevo usuario...');

                $role = Role::where('num_rol', 1)->first();
                if (!$role) {
                    Log::error('El rol de cliente no existe');
                    return response()->json(['error' => 'El rol no existe.'], 404);
                }

                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'password' => bcrypt('googlepassword'),
                    'role_id' => $role->id,
                ]);

                Log::info('Usuario creado con éxito: ' . $user->email);
            }

            if (!$user->cliente) {
                Log::info('El usuario no tiene datos de cliente, creando cliente...');

                try {
                    $user->cliente()->create([
                        'nombre' => $googleUser->getName(),
                        'dni' => null,
                        'celular' => null,
                        'direccion' => null,
                        'preferencias_compra' => null,
                        'user_id' => $user->id,
                    ]);

                    Log::info('Cliente creado para el usuario: ' . $user->email);
                } catch (\Exception $e) {
                    Log::error('Error creando cliente: ' . $e->getMessage());
                    return response()->json(['error' => 'Error creando cliente: ' . $e->getMessage()], 500);
                }
            }

            // ✅ Crear carrito si no tiene
            if (!$user->carrito) {
                Carrito::create([
                    'user_id' => $user->id,
                ]);
                Log::info('Carrito creado para el usuario: ' . $user->email);
            }

            Auth::login($user, true);

            $token = $user->createToken('GoogleLogin')->plainTextToken;
            Log::info('Token creado para el usuario: ' . $user->email);

            $user->load('cliente', 'role');

            return response()->json([
                'access_token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'num_rol' => $user->role->num_rol ?? null,
                    'user_type' => 'Cliente',
                    'related_data' => $user->cliente,
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error al autenticar con Google: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al autenticar con Google',
                'success' => false,
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function handleGoogleLogin(Request $request)
    {
        try {
            Log::info('Recibiendo ID Token para validación...');

            $idToken = $request->input('token');
            $client = new \Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                Log::error('ID Token inválido');
                return response()->json(['success' => false, 'error' => 'ID Token inválido.'], 401);
            }

            Log::info('ID Token validado, usuario: ' . $payload['email']);

            $email = $payload['email'];
            $user = User::where('email', $email)->first();

            if (!$user) {
                Log::info('El usuario no existe, creando nuevo usuario...');

                $role = Role::where('num_rol', 1)->first();
                if (!$role) {
                    Log::error('El rol de cliente no existe');
                    return response()->json(['error' => 'El rol no existe.'], 404);
                }

                $user = User::create([
                    'name' => $payload['name'],
                    'email' => $email,
                    'password' => bcrypt(uniqid()),
                    'role_id' => $role->id,
                ]);

                Log::info('Usuario creado: ' . $user->email);
            }

            if (!$user->cliente) {
                Log::info('El usuario no tiene datos de cliente, creando cliente...');

                try {
                    $user->cliente()->create([
                        'nombre' => $payload['name'],
                        'dni' => null,
                        'celular' => null,
                        'direccion' => null,
                        'preferencias_compra' => null,
                        'user_id' => $user->id,
                    ]);

                    Log::info('Cliente creado para el usuario: ' . $user->email);
                } catch (\Exception $e) {
                    Log::error('Error creando cliente: ' . $e->getMessage());
                    return response()->json(['error' => 'Error creando cliente: ' . $e->getMessage()], 500);
                }
            }

            // ✅ Crear carrito si no tiene
            if (!$user->carrito) {
                Carrito::create([
                    'user_id' => $user->id,
                ]);
                Log::info('Carrito creado para el usuario: ' . $user->email);
            }

            $token = $user->createToken('GoogleLogin')->plainTextToken;
            $user->load('cliente', 'role');

            Log::info('Datos del usuario cargados: ' . json_encode($user->toArray()));

            return response()->json([
                'access_token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'num_rol' => $user->role->num_rol ?? null,
                    'user_type' => 'Cliente',
                    'related_data' => $user->cliente,
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error durante la autenticación de Google: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error durante la autenticación de Google: ' . $e->getMessage()
            ], 500);
        }
    }

    // GoogleAuthController.php
    public function completarDatos(Request $request)
    {
        $request->validate([
            'dni'       => 'required|string|max:15',
            'celular'   => 'required|string|max:20',
            'direccion' => 'required|string|max:255',
        ]);

        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $cliente = $user->cliente;
        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        $cliente->update([
            'dni'       => $request->dni,
            'celular'   => $request->celular,
            'direccion' => $request->direccion,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Datos completados correctamente',
            'cliente' => $cliente
        ]);
    }

}
