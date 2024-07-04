<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Categoria;

class CategoriaController extends Controller
{
    // Mostrar todas las categorias
    public function index()
    {
        $categorias = Categoria::all();
        return response()->json($categorias);
    }

    // Mostrar una categoria específica
    public function show($id)
    {
        $categoria = Categoria::findOrFail($id);
        return response()->json($categoria);
    }

    // Crear una nueva categoria
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|unique:categorias,nombre'
        ]);

        $categoria = new Categoria();
        $categoria->nombre = $request->nombre;
        $categoria->save();

        return response()->json($categoria, 201);
    }

    // Actualizar una categoria existente
    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|unique:categorias,nombre'
        ]);

        $categoria = Categoria::findOrFail($id);
        $categoria->nombre = $request->nombre;
        $categoria->save();

        return response()->json($categoria, 200);
    }

    // Eliminar una categoria existente
    public function destroy($id)
    {
        $categoria = Categoria::findOrFail($id);
        $categoria->delete();

        return response()->json(null, 204);
    }

    public function todasLasCategoriasConProductos()
    {
        $categorias = Categoria::with('productos')->get();

        $resultado = $categorias->map(function ($categoria) {
            return [
                'id' => $categoria->id,
                'nombre' => $categoria->nombre,
                'productos' => $categoria->productos->map(function ($producto) {
                    return [
                        'id' => $producto->id,
                        'nombre' => $producto->nombre,
                        'descripcion' => $producto->descripcion,
                        'precio' => $producto->precio,
                        'imagen' => $producto->imagen,
                    ];
                }),
            ];
        });

        return response()->json($resultado);
    }
}
