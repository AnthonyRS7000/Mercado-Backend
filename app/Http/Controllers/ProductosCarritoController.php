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
    
        $productosCarrito = productos_carrito::with('producto')->where('carrito_id', $carrito->id)->get();
    
        if ($productosCarrito->isEmpty()) {
            return response()->json(['message' => 'El carrito está vacío'], 200);
        }
    
        $cantidadTotal = $productosCarrito->sum('cantidad');
        $totalPrecio = $productosCarrito->sum('total');
    
        $productos = $productosCarrito->map(function($item) {
            return [
                'id' => $item->id,
                'cantidad' => $item->cantidad,
                'fecha_agrego' => $item->fecha_agrego,
                'total' => $item->total,
                'estado' => $item->estado,
                'carrito_id' => $item->carrito_id,
                'producto_id' => $item->producto_id,
                'producto' => $item->producto, // Incluyendo todos los detalles del producto
            ];
        });
    
        return response()->json([
            'productos' => $productos,
            'cantidad_total' => $cantidadTotal,
            'total_precio' => $totalPrecio
        ], 200);
    }
    

    public function agregar(Request $request)
    {
        // Validar la solicitud
        $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'cantidad' => 'required|numeric|min:0.01',
            'uuid' => 'sometimes|uuid',
            'user_id' => 'sometimes|exists:users,id',
        ]);
    
        // Obtener o crear el carrito con user_id o uuid
        if ($request->has('user_id')) {
            $carrito = Carrito::firstOrCreate(
                ['user_id' => $request->user_id],
                ['user_id' => $request->user_id]
            );
        } else {
            $carrito = Carrito::firstOrCreate(
                ['uuid' => $request->uuid],
                ['uuid' => $request->uuid]
            );
        }
    
        $producto = Producto::find($request->producto_id);
    
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }
    
        // Crear o actualizar el producto en el carrito
        $productosCarrito = productos_carrito::updateOrCreate(
            [
                'carrito_id' => $carrito->id,
                'producto_id' => $request->producto_id
            ],
            [
                'cantidad' => $request->cantidad,
                'fecha_agrego' => now(),
                'total' => $producto->precio * $request->cantidad,
                'estado' => 1 // o el valor correspondiente según tu lógica
            ]
        );
    
        return response()->json(['message' => 'Producto agregado al carrito', 'carrito' => $productosCarrito, 'uuid' => $carrito->uuid], 200);
    }
    
    

    public function actualizar(Request $request, $carritoId, $productoId)
    {
        \Log::info('Actualizar método llamado con:', [
            'carritoId' => $carritoId,
            'productoId' => $productoId,
            'cantidad' => $request->cantidad
        ]);
    
        $request->validate([
            'cantidad' => 'required|numeric|min:0.01',
        ]);
    
        $productosCarrito = productos_carrito::where('carrito_id', $carritoId)->where('producto_id', $productoId)->first();
    
        if (!$productosCarrito) {
            return response()->json(['message' => 'Producto no encontrado en el carrito'], 404);
        }
    
        $producto = Producto::find($productoId);
        if ($producto->tipo == 'unidad' && !is_int($request->cantidad)) {
            return response()->json(['message' => 'Cantidad debe ser un entero para productos por unidad'], 400);
        }
    
        $productosCarrito->update(['cantidad' => $request->cantidad, 'total' => $producto->precio * $request->cantidad]);
    
        return response()->json(['message' => 'Cantidad actualizada', 'carrito' => $productosCarrito], 200);
    }
    
    
    
    

    // Eliminar producto del carrito
    public function eliminar($carritoId, $productoId)
    {
        $productosCarrito = productos_carrito::where('carrito_id', $carritoId)->where('producto_id', $productoId)->first();

        if (!$productosCarrito) {
            return response()->json(['message' => 'Producto no encontrado en el carrito'], 404);
        }

        $productosCarrito->delete();

        return response()->json(['message' => 'Producto eliminado del carrito'], 200);
    }


    public function vaciar(Request $request)
    {
        $uuid = $request->input('uuid');
        $userId = $request->input('user_id');
    
        if (!$uuid && !$userId) {
            return response()->json(['message' => 'UUID o user_id es requerido'], 400);
        }
    
        if ($uuid) {
            $carrito = Carrito::where('uuid', $uuid)->first();
        } else {
            $carrito = Carrito::where('user_id', $userId)->first();
        }
    
        if (!$carrito) {
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }
    
        productos_carrito::where('carrito_id', $carrito->id)->delete();
    
        return response()->json(['message' => 'Carrito vaciado'], 200);
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

    public function mergeCart(Request $request)
    {
        $uuid = $request->input('uuid');
        $user_id = $request->input('user_id'); // Obtener el ID del usuario desde la solicitud
    
        if (!$uuid || !$user_id) {
            return response()->json(['message' => 'UUID y user_id son requeridos'], 400);
        }
    
        $guestCart = Carrito::where('uuid', $uuid)->first();
        $userCart = Carrito::where('user_id', $user_id)->first();
    
        if (!$guestCart) {
            return response()->json(['message' => 'Carrito de invitado no encontrado'], 404);
        }
    
        if ($guestCart && $userCart) {
            // Si ambos carritos existen, combinar los productos
            foreach ($guestCart->productos as $product) {
                $existingProduct = $userCart->productos()->where('producto_id', $product->pivot->producto_id)->first();
                if ($existingProduct) {
                    // Actualizar cantidad y total si el producto ya existe en el carrito del usuario
                    $existingProduct->pivot->cantidad += $product->pivot->cantidad;
                    $existingProduct->pivot->total += $product->pivot->total;
                    $existingProduct->pivot->save();
                } else {
                    // Agregar nuevo producto al carrito del usuario
                    $userCart->productos()->attach($product->pivot->producto_id, [
                        'cantidad' => $product->pivot->cantidad,
                        'total' => $product->pivot->total,
                        'fecha_agrego' => now(),
                        'estado' => 1,
                    ]);
                }
            }
            $guestCart->delete(); // Eliminar el carrito de invitado después de combinar
        } elseif ($guestCart) {
            // Si solo existe el carrito de invitado, asignarlo al usuario autenticado
            $guestCart->user_id = $user_id;
            $guestCart->uuid = null; // Limpiar el UUID ya que ahora está asociado al cliente
            $guestCart->save();
        }
    
        return response()->json(['message' => 'Carrito combinado exitosamente'], 200);
    }
    
    

    public function getCartByUserId($userId)
    {
        try {
            $carrito = Carrito::where('user_id', $userId)->first();
    
            if (!$carrito) {
                return response()->json(['message' => 'Carrito no encontrado'], 404);
            }
    
            $productosCarrito = productos_carrito::with('producto')->where('carrito_id', $carrito->id)->get();
    
            if ($productosCarrito->isEmpty()) {
                return response()->json(['message' => 'El carrito está vacío'], 200);
            }
    
            $cantidadTotal = $productosCarrito->sum('cantidad');
            $totalPrecio = $productosCarrito->sum('total');
    
            return response()->json([
                'carrito_id' => $carrito->id, // Asegúrate de devolver el carrito_id
                'productos' => $productosCarrito,
                'cantidad_total' => $cantidadTotal,
                'total_precio' => $totalPrecio,
                'uuid' => $carrito->uuid,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error interno del servidor', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getCartByUuid($uuid)
    {
        try {
            $carrito = Carrito::where('uuid', $uuid)->first();
    
            if (!$carrito) {
                return response()->json(['message' => 'Carrito no encontrado'], 404);
            }
    
            $productosCarrito = productos_carrito::with('producto')->where('carrito_id', $carrito->id)->get();
    
            if ($productosCarrito->isEmpty()) {
                return response()->json(['message' => 'El carrito está vacío'], 200);
            }
    
            $cantidadTotal = $productosCarrito->sum('cantidad');
            $totalPrecio = $productosCarrito->sum('total');
    
            return response()->json([
                'carrito_id' => $carrito->id, // Asegúrate de devolver el carrito_id
                'productos' => $productosCarrito,
                'cantidad_total' => $cantidadTotal,
                'total_precio' => $totalPrecio,
                'uuid' => $carrito->uuid,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error interno del servidor', 'error' => $e->getMessage()], 500);
        }
    }
    
}






