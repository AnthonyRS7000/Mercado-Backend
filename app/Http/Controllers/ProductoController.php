<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductoController extends Controller
{
    public function index()
    {
        $productos = Producto::with('categoria')->get();
        return response()->json($productos, 200);
    }

    public function show($id)
    {
        $producto = Producto::with('categoria')->find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        return response()->json($producto, 200);
    }

    public function store(Request $request)
    {
        // Añadir registro para depuración
        \Log::info('Request data:', $request->all());

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'required|string|max:255',
            'estado' => 'required|integer',
            'stock' => 'required|integer',
            'precio' => 'required|numeric',
            'categoria_id' => 'required|exists:categorias,id',
            'proveedor_id' => 'required|exists:proveedors,id',
            'imagen' => 'required|file|max:2048', // Cambiado a file
            'tipo' => 'required|in:peso,unidad',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('imagen')) {
            \Log::info('Imagen detectada');
            $file = $request->file('imagen');
            $mimeType = $file->getClientMimeType();
            \Log::info('Imagen tipo MIME:', [$mimeType]);

            $validMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/svg+xml'];
            if (!in_array($mimeType, $validMimeTypes)) {
                \Log::error('Tipo de archivo no permitido:', [$mimeType]);
                return response()->json(['errors' => ['imagen' => 'El tipo de archivo no es permitido']], 422);
            }

            $imagenPath = $file->store('imagenes', 'public');
            $imagenUrl = Storage::url($imagenPath);
        } else {
            \Log::warning('No se detectó una imagen en la solicitud');
        }

        $producto = Producto::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'estado' => $request->estado,
            'stock' => $request->stock,
            'precio' => $request->precio,
            'categoria_id' => $request->categoria_id,
            'proveedor_id' => $request->proveedor_id,
            'imagen' => $imagenUrl ?? null,
            'tipo' => $request->tipo,
        ]);

        return response()->json($producto, 201);
    }

    // Otros métodos no mostrados por brevedad
}
