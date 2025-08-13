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
        $validator = Validator::make($request->all(), [
            'nombre'        => 'required|string|max:255',
            'descripcion'   => 'required|string|max:255',
            'estado'        => 'required|integer',
            'stock'         => 'nullable|integer',
            'precio'        => 'required|numeric',
            'categoria_id'  => 'required|exists:categorias,id',
            'proveedor_id'  => 'required|exists:proveedors,id',
            'imagen'        => 'required|file|max:2048',
            'tipo'          => 'required|in:peso,unidad',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagenUrl = null;
        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $mimeType = $file->getClientMimeType();
            $validMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/svg+xml'];
            if (!in_array($mimeType, $validMimeTypes)) {
                return response()->json(['errors' => ['imagen' => 'El tipo de archivo no es permitido']], 422);
            }

            $imagenPath = $file->store('imagenes', 'public');
            $imagenUrl = Storage::url($imagenPath);
        }

        $producto = Producto::create([
            'nombre'        => $request->nombre,
            'descripcion'   => $request->descripcion,
            'estado'        => $request->estado,
            'stock'         => $request->input('stock', 0),
            'precio'        => $request->precio,
            'categoria_id'  => $request->categoria_id,
            'proveedor_id'  => $request->proveedor_id,
            'imagen'        => $imagenUrl,
            'tipo'          => $request->tipo,
        ]);

        return response()->json($producto, 201);
    }

    public function destroy($id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        try {
            if ($producto->imagen && Storage::disk('public')->exists(str_replace('/storage/', '', $producto->imagen))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $producto->imagen));
            }

            $producto->delete();
            return response()->json(['message' => 'Producto eliminado exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Ocurrió un error al eliminar el producto.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre'        => 'sometimes|required|string|max:255',
            'descripcion'   => 'sometimes|required|string|max:255',
            'estado'        => 'sometimes|required|integer',
            'stock'         => 'sometimes|required|integer',
            'precio'        => 'sometimes|required|numeric',
            'categoria_id'  => 'sometimes|required|exists:categorias,id',
            'proveedor_id'  => 'sometimes|required|exists:proveedors,id',
            'imagen'        => 'nullable|file|max:2048',
            'tipo'          => 'sometimes|required|in:peso,unidad',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $mimeType = $file->getClientMimeType();
            $validMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/svg+xml'];
            if (!in_array($mimeType, $validMimeTypes)) {
                return response()->json(['errors' => ['imagen' => 'El tipo de archivo no es permitido']], 422);
            }

            if ($producto->imagen && Storage::disk('public')->exists(str_replace('/storage/', '', $producto->imagen))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $producto->imagen));
            }

            $imagenPath = $file->store('imagenes', 'public');
            $producto->imagen = Storage::url($imagenPath);
        }

        foreach (['nombre', 'descripcion', 'estado', 'stock', 'precio', 'categoria_id', 'proveedor_id', 'tipo'] as $campo) {
            if ($request->has($campo)) {
                $producto->{$campo} = $request->{$campo};
            }
        }

        $producto->save();

        return response()->json($producto, 200);
    }

    /**
     * Listar productos por proveedor.
     */
    public function productosPorProveedor($proveedor_id)
    {
        $productos = Producto::with('categoria')
            ->where('proveedor_id', $proveedor_id)
            ->get();

        if ($productos->isEmpty()) {
            return response()->json(['error' => 'No se encontraron productos para este proveedor.'], 404);
        }

        return response()->json($productos, 200);
    }

    public function productosPorCategoria($categoria_id)
    {
        $productos = Producto::with('categoria')->where('categoria_id', $categoria_id)->get();

        if ($productos->isEmpty()) {
            return response()->json(['error' => 'No se encontraron productos para esta categoría.'], 404);
        }

        return response()->json($productos, 200);
    }

    // Calcular precio según cantidad
    public function calcularPrecio(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'cantidad' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cantidad = $request->cantidad;
        $precioTotal = $producto->precio * $cantidad;

        return response()->json([
            'producto_id' => $producto->id,
            'nombre'      => $producto->nombre,
            'tipo'        => $producto->tipo,
            'cantidad'    => $cantidad,
            'precio_total'=> $precioTotal,
        ], 200);
    }

    public function mostrar_one($id)
    {
        $producto = Producto::with(['categoria', 'proveedor'])->find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $relacionados = Producto::with(['categoria', 'proveedor'])
            ->where('categoria_id', $producto->categoria_id)
            ->where('id', '!=', $producto->id)
            ->where(function ($query) use ($producto) {
                $query->where('nombre', 'LIKE', '%' . $producto->nombre . '%')
                      ->orWhere('nombre', 'LIKE', '%' . substr($producto->nombre, 0, 3) . '%');
            })
            ->take(4)
            ->get();

        return response()->json([
            'producto'    => $producto,
            'relacionados'=> $relacionados
        ], 200);
    }
}
