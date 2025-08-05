<?php

namespace App\Http\Controllers;

use App\Models\SolicitudRegistro;
use App\Models\User;
use App\Models\Proveedor;
use App\Models\Delivery;
use App\Models\Personal_Sistema;
use App\Models\Cliente;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SolicitudRegistroController extends Controller
{
    // 1. Guardar nueva solicitud
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'nullable|string|max:255',
            'nombre_empresa' => 'nullable|string|max:255',
            'dni' => 'nullable|string|max:255',
            'celular' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'preferencias_compra' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'password' => 'nullable|string|max:255',
            'tipo' => 'required|in:cliente,proveedor,delivery,personal_sistema',
            'ids' => 'nullable|array',
            'ids.*' => 'exists:categorias,id',
        ]);

        $errors = [];

        if ($request->filled('email') && User::where('email', $request->email)->exists()) {
            $errors['email'][] = 'El email ya está en uso.';
        }

        if ($request->filled('dni')) {
            $dniExists = match ($request->tipo) {
                'proveedor' => Proveedor::where('dni', $request->dni)->exists(),
                'delivery' => Delivery::where('dni', $request->dni)->exists(),
                'personal_sistema' => Personal_Sistema::where('dni', $request->dni)->exists(),
                'cliente' => Cliente::where('dni', $request->dni)->exists(),
            };
            if ($dniExists) {
                $errors['dni'][] = 'El DNI ya está en uso.';
            }
        }

        if ($request->filled('celular')) {
            $celularExists = match ($request->tipo) {
                'proveedor' => Proveedor::where('celular', $request->celular)->exists(),
                'delivery' => Delivery::where('celular', $request->celular)->exists(),
                'personal_sistema' => Personal_Sistema::where('celular', $request->celular)->exists(),
                'cliente' => Cliente::where('celular', $request->celular)->exists(),
            };
            if ($celularExists) {
                $errors['celular'][] = 'El celular ya está en uso.';
            }
        }

        if (!empty($errors)) {
            return response()->json(['errors' => $errors], 422);
        }

        $validated['estado'] = 'pendiente';

        if ($request->tipo === 'proveedor' && $request->filled('ids')) {
            $validated['ids'] = $request->ids;
        }

        // 🔐 Cifrar la contraseña solo una vez
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $solicitud = SolicitudRegistro::create($validated);

        return response()->json([
            'message' => 'Solicitud registrada correctamente',
            'solicitud' => $solicitud
        ], 201);
    }

    // 2. Listar todas las solicitudes
    public function index()
    {
        $solicitudes = SolicitudRegistro::orderBy('created_at', 'desc')->get();
        return response()->json($solicitudes);
    }

    // 3. Aprobar solicitud
    public function aprobar($id)
    {
        $solicitud = SolicitudRegistro::findOrFail($id);

        if ($solicitud->estado !== 'pendiente') {
            return response()->json(['error' => 'Esta solicitud ya fue procesada'], 400);
        }

        if (User::where('email', $solicitud->email)->exists()) {
            return response()->json(['error' => 'El email ya está en uso.'], 409);
        }

        $role_id = match ($solicitud->tipo) {
            'proveedor' => Role::where('num_rol', 2)->value('id'),
            'delivery' => Role::where('num_rol', 4)->value('id'),
            'personal_sistema' => Role::where('num_rol', 3)->value('id'),
            'cliente' => Role::where('num_rol', 1)->value('id'),
            default => null,
        };

        if (!$role_id) {
            return response()->json(['error' => 'No se encontró un rol válido para el tipo de usuario'], 400);
        }

        // NO volver a hashear la contraseña
        $user = User::create([
            'name' => $solicitud->nombre ?? '-',
            'email' => $solicitud->email,
            'password' => $solicitud->password, // ya viene hasheada
            'role_id' => $role_id,
        ]);

        switch ($solicitud->tipo) {
            case 'proveedor':
                $proveedor = Proveedor::create([
                    'nombre' => $solicitud->nombre,
                    'nombre_empresa' => $solicitud->nombre_empresa,
                    'direccion' => $solicitud->direccion,
                    'dni' => $solicitud->dni,
                    'celular' => $solicitud->celular,
                    'user_id' => $user->id,
                ]);

                if (!empty($solicitud->ids)) {
                    $proveedor->categorias()->sync($solicitud->ids);
                }
                break;

            case 'delivery':
                Delivery::create([
                    'nombre' => $solicitud->nombre,
                    'nombre_empresa' => $solicitud->nombre_empresa,
                    'dni' => $solicitud->dni,
                    'celular' => $solicitud->celular,
                    'user_id' => $user->id,
                ]);
                break;

            case 'personal_sistema':
                Personal_Sistema::create([
                    'nombre' => $solicitud->nombre,
                    'dni' => $solicitud->dni,
                    'celular' => $solicitud->celular,
                    'user_id' => $user->id,
                ]);
                break;

            case 'cliente':
                Cliente::create([
                    'nombre' => $solicitud->nombre,
                    'dni' => $solicitud->dni,
                    'celular' => $solicitud->celular,
                    'direccion' => $solicitud->direccion,
                    'preferencias_compra' => $solicitud->preferencias_compra,
                    'user_id' => $user->id,
                ]);
                break;

            default:
                return response()->json(['error' => 'Tipo de solicitud no válido.'], 400);
        }

        $solicitud->estado = 'aprobada';
        $solicitud->save();

        return response()->json(['message' => 'Solicitud aprobada y usuario registrado exitosamente']);
    }

    // 4. Rechazar solicitud
    public function rechazar($id)
    {
        $solicitud = SolicitudRegistro::findOrFail($id);
        $solicitud->estado = 'rechazada';
        $solicitud->save();

        return response()->json([
            'message' => 'Solicitud rechazada',
            'solicitud' => $solicitud
        ]);
    }
}
