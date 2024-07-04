<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
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
        $Proveedors = Proveedor::all();
        return response()->json($Proveedors, 200);
    }

    public function show($id)
    {
        $Proveedor = Proveedor::find($id);

        if (!$Proveedor) {
            return response()->json(['error' => 'Proveedor no encontrado.'], 404);
        }

        return response()->json($Proveedor, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'nombre_empresa' => 'required|string|max:255',
            'dni' => 'required|string|max:255|unique:Proveedors,dni',
            'celular' => 'required|string|max:255|unique:Proveedors,celular',
            'direccion' => 'required|string|max:255',
            'catalogo_productos' => 'nullable|string|max:255',
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
            'catalogo_productos' => $request->catalogo_productos,
            'user_id' => $user->id,
        ]);
    
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
            'dni' => 'sometimes|required|string|max:255|unique:Proveedors,dni,' . $id,
            'celular' => 'sometimes|required|string|max:255|unique:Proveedors,celular,' . $id,
            'direccion' => 'sometimes|required|string|max:255',
            'catalogo_productos' => 'nullable|string|max:255',
            'user_id' => 'sometimes|required|exists:users,id',
        ]);

        $Proveedor->update($validatedData);

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
}
