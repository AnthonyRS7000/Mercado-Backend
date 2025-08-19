<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Imagen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ImagenController extends Controller
{
    /**
     * Mostrar todas las imágenes.
     */
    public function index()
    {
        $imagenes = Imagen::select('id_imagen', 'nombre', 'url')->get();
        return response()->json($imagenes);
    }

    /**
     * Registrar una nueva imagen.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre'      => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:255',
            'imagen'      => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'imagen_url'  => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagenUrl = null;

        // Caso 1: archivo subido
        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $path = $file->store('imagenes', 'public'); // guarda en storage/app/public/imagenes
            $imagenUrl = Storage::url($path);
        }
        // Caso 2: URL proporcionada
        elseif ($request->filled('imagen_url')) {
            $imagenUrl = $request->imagen_url;
        }

        $imagen = Imagen::create([
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'url'         => $imagenUrl,
        ]);

        return response()->json([
            'message' => '✅ Imagen registrada con éxito',
            'data'    => $imagen
        ], 201);
    }
}
