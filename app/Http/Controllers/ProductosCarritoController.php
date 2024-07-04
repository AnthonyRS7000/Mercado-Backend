<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\productos_carrito;
use App\Models\Producto;
use Illuminate\Support\Facades\Auth;

class ProductosCarritoController extends Controller
{
    // Mostrar el carrito
    public function index()
    {
        $cliente = Auth::user();
        $carrito = productos_carrito::where('cliente_id', $cliente->id)->get();

        if ($carrito->isEmpty()) {
            return response()->json(['message' => 'El carrito está vacío'], 200);
        }

        return response()->json($carrito, 200);
    }

    // Agregar producto al carrito
    public function agregar(Request $request)
    {
        $cliente = Auth::user();
        $producto = Producto::find($request->producto_id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $carrito = productos_carrito::create([
            'cliente_id' => $cliente->id,
            'producto_id' => $request->producto_id,
            'cantidad' => $request->cantidad,
            'fecha_agrego' => now(),
            'total' => $producto->precio * $request->cantidad,
            'estado' => 0 // Asignar un valor entero en lugar de 'pendiente'
        ]);

        return response()->json(['message' => 'Producto agregado al carrito', 'carrito' => $carrito], 200);
    }

    // Actualizar cantidad de un producto en el carrito
    public function actualizar(Request $request, $id)
    {
        $cliente = Auth::user();
        $carrito = productos_carrito::where('cliente_id', $cliente->id)->where('producto_id', $id)->first();

        if (!$carrito) {
            return response()->json(['message' => 'Producto no encontrado en el carrito'], 404);
        }

        $carrito->update(['cantidad' => $request->cantidad]);

        return response()->json(['message' => 'Cantidad actualizada', 'carrito' => $carrito], 200);
    }

    // Eliminar producto del carrito
    public function eliminar($id)
    {
        $cliente = Auth::user();
        $carrito = productos_carrito::where('cliente_id', $cliente->id)->where('producto_id', $id)->first();

        if (!$carrito) {
            return response()->json(['message' => 'Producto no encontrado en el carrito'], 404);
        }

        $carrito->delete();

        return response()->json(['message' => 'Producto eliminado del carrito'], 200);
    }

    // Vaciar carrito
    public function vaciar()
    {
        $cliente = Auth::user();
        productos_carrito::where('cliente_id', $cliente->id)->delete();

        return response()->json(['message' => 'Carrito vaciado'], 200);
    }
}
