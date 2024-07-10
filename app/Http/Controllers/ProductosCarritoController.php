<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\productos_carrito;
use App\Models\Producto;
use App\Models\Carrito;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class ProductosCarritoController extends Controller
{
    public function index(Request $request)
    {
        $uuid = $request->query('uuid'); // Recibir el UUID como parámetro de consulta

        if (!$uuid) {
            return response()->json(['message' => 'UUID es requerido'], 400);
        }

        $carrito = Carrito::where('uuid', $uuid)->first();

        if (!$carrito) {
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }

        $productosCarrito = productos_carrito::where('carrito_id', $carrito->id)->get();

        if ($productosCarrito->isEmpty()) {
            return response()->json(['message' => 'El carrito está vacío'], 200);
        }

        return response()->json($productosCarrito, 200);
    }

    // Agregar producto al carrito
// src/Http/Controllers/ProductosCarritoController.php

    public function agregar(Request $request)
    {
        // Obtener o generar el carrito con UUID
        $carrito = Carrito::firstOrCreate(
            ['uuid' => $request->uuid],
            ['uuid' => $request->uuid]
        );
        
        $producto = Producto::find($request->producto_id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $productosCarrito = productos_carrito::create([
            'carrito_id' => $carrito->id,
            'producto_id' => $request->producto_id,
            'cantidad' => $request->cantidad,
            'fecha_agrego' => now(),
            'total' => $producto->precio * $request->cantidad,
            'estado' => 0 // Asignar un valor entero en lugar de 'pendiente'
        ]);

        return response()->json(['message' => 'Producto agregado al carrito', 'carrito' => $productosCarrito, 'uuid' => $carrito->uuid], 200);
    }


    // Actualizar cantidad de un producto en el carrito
    public function actualizar(Request $request, $id)
    {
        $carrito = $this->getCarrito();
        $productosCarrito = productos_carrito::where('carrito_id', $carrito->id)->where('producto_id', $id)->first();

        if (!$productosCarrito) {
            return response()->json(['message' => 'Producto no encontrado en el carrito'], 404);
        }

        $productosCarrito->update(['cantidad' => $request->cantidad]);

        return response()->json(['message' => 'Cantidad actualizada', 'carrito' => $productosCarrito], 200);
    }

    // Eliminar producto del carrito
    public function eliminar(Request $request, $id)
    {
        $carrito = $this->getCarrito();
        $productosCarrito = productos_carrito::where('carrito_id', $carrito->id)->where('producto_id', $id)->first();

        if (!$productosCarrito) {
            return response()->json(['message' => 'Producto no encontrado en el carrito'], 404);
        }

        $productosCarrito->delete();

        return response()->json(['message' => 'Producto eliminado del carrito'], 200);
    }

    // Vaciar carrito
    public function vaciar(Request $request)
    {
        $carrito = $this->getCarrito();
        productos_carrito::where('carrito_id', $carrito->id)->delete();

        return response()->json(['message' => 'Carrito vaciado'], 200);
    }

    // Transferir productos del carrito temporal al cliente autenticado
    public function transferirCarrito(Request $request)
    {
        if (Auth::check()) {
            $carrito = $this->getCarrito();
            $carrito->cliente_id = Auth::id();
            $carrito->save();

            // Limpiar el carrito_id temporal de la sesión
            Session::forget('carrito_id');

            return response()->json(['message' => 'Carrito transferido exitosamente'], 200);
        }

        return response()->json(['message' => 'Usuario no autenticado'], 401);
    }

    // Método para obtener el carrito, generando uno si es necesario
    private function getCarrito()
    {
        if (!Session::has('carrito_id')) {
            // Crear un nuevo carrito con UUID
            $uuid = (string) Str::uuid();
            $carrito = Carrito::create([
                'uuid' => $uuid
            ]);
            Session::put('carrito_id', $carrito->id);
        } else {
            $carrito = Carrito::find(Session::get('carrito_id'));
        }

        return $carrito;
    }
}




