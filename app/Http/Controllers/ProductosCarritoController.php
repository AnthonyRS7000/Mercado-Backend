<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\productos_carrito;
use App\Models\Producto;
use App\Models\Carrito;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\Personal_sistema;
use App\Models\Delivery;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

class ProductosCarritoController extends Controller
{
    public function index(Request $request)
    {
        $uuid = $request->query('uuid');

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
                'producto' => $item->producto,
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
        $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'cantidad' => 'required|numeric|min:0.01',
            'uuid' => 'sometimes|uuid',
            'user_id' => 'sometimes|exists:users,id',
        ]);

        // Verificar si el user_id existe en otras tablas de roles
        $user_id = $request->user_id;
        $carrito = null;

        if ($user_id) {
            $esCliente = Cliente::where('user_id', $user_id)->exists();
            $esProveedor = Proveedor::where('user_id', $user_id)->exists();
            $esRecolector = Personal_sistema::where('user_id', $user_id)->exists();
            $esRepartidor = Delivery::where('user_id', $user_id)->exists();

            if ($esCliente || $esProveedor || $esRecolector || $esRepartidor) {
                $carrito = Carrito::firstOrCreate(
                    ['user_id' => $user_id],
                    ['user_id' => $user_id]
                );
            }
        }

        // Si no se encontró carrito por user_id, usar UUID
        if (!$carrito && $request->uuid) {
            $carrito = Carrito::firstOrCreate(
                ['uuid' => $request->uuid],
                ['uuid' => $request->uuid]
            );
        }

        // Si aún no hay carrito, crear uno con UUID
        if (!$carrito) {
            $uuid = $request->uuid ?: (string) Str::uuid();
            $carrito = Carrito::create(['uuid' => $uuid]);
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
                'estado' => 1
            ]
        );

        return response()->json([
            'message' => 'Producto agregado al carrito',
            'carrito' => $productosCarrito,
            'uuid' => $carrito->uuid
        ], 200);
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

        $productosCarrito = productos_carrito::where('carrito_id', $carritoId)
            ->where('producto_id', $productoId)
            ->first();

        if (!$productosCarrito) {
            return response()->json(['message' => 'Producto no encontrado en el carrito'], 404);
        }

        $producto = Producto::find($productoId);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        if ($producto->tipo == 'unidad' && !is_int($request->cantidad)) {
            return response()->json(['message' => 'Cantidad debe ser un entero para productos por unidad'], 400);
        }

        $productosCarrito->update([
            'cantidad' => $request->cantidad,
            'total' => $producto->precio * $request->cantidad
        ]);

        return response()->json(['message' => 'Cantidad actualizada', 'carrito' => $productosCarrito], 200);
    }

    public function eliminar($carritoId, $productoId)
    {
        $productosCarrito = productos_carrito::where('carrito_id', $carritoId)
            ->where('producto_id', $productoId)
            ->first();

        if (!$productosCarrito) {
            return response()->json(['message' => 'Producto no encontrado en el carrito'], 404);
        }

        $productosCarrito->delete();

        return response()->json(['message' => 'Producto eliminado del carrito'], 200);
    }

    public function vaciar(Request $request)
    {
        $uuid = $request->query('uuid'); // Cambiar a query parameter
        $userId = $request->input('user_id');

        if (!$uuid && !$userId) {
            return response()->json(['message' => 'UUID o user_id es requerido'], 400);
        }

        $carrito = null;

        if ($userId) {
            $carrito = Carrito::where('user_id', $userId)->first();
        } elseif ($uuid) {
            $carrito = Carrito::where('uuid', $uuid)->first();
        }

        if (!$carrito) {
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }

        productos_carrito::where('carrito_id', $carrito->id)->delete();

        return response()->json(['message' => 'Carrito vaciado'], 200);
    }

    private function getCarrito()
    {
        if (!Session::has('carrito_id')) {
            $uuid = (string) Str::uuid();
            $carrito = Carrito::create(['uuid' => $uuid]);
            Session::put('carrito_id', $carrito->id);
        } else {
            $carrito = Carrito::find(Session::get('carrito_id'));
        }

        return $carrito;
    }

    public function mergeCart(Request $request)
    {
        $uuid = $request->input('uuid');
        $user_id = $request->input('user_id');

        if (!$uuid || !$user_id) {
            return response()->json(['message' => 'UUID y user_id son requeridos'], 400);
        }

        $guestCart = Carrito::where('uuid', $uuid)->first();
        $userCart = Carrito::where('user_id', $user_id)->first();

        if (!$guestCart) {
            return response()->json(['message' => 'Carrito de invitado no encontrado'], 404);
        }

        // Si no existe carrito de usuario, crear uno
        if (!$userCart) {
            $userCart = Carrito::create(['user_id' => $user_id]);
        }

        // Obtener productos del carrito de invitado
        $guestProducts = productos_carrito::where('carrito_id', $guestCart->id)->get();

        foreach ($guestProducts as $guestProduct) {
            $existingProduct = productos_carrito::where('carrito_id', $userCart->id)
                ->where('producto_id', $guestProduct->producto_id)
                ->first();

            if ($existingProduct) {
                // Actualizar cantidad y total si el producto ya existe
                $existingProduct->cantidad += $guestProduct->cantidad;
                $existingProduct->total += $guestProduct->total;
                $existingProduct->save();
            } else {
                // Crear nuevo producto en el carrito del usuario
                productos_carrito::create([
                    'carrito_id' => $userCart->id,
                    'producto_id' => $guestProduct->producto_id,
                    'cantidad' => $guestProduct->cantidad,
                    'total' => $guestProduct->total,
                    'fecha_agrego' => now(),
                    'estado' => 1,
                ]);
            }
        }

        // Eliminar el carrito de invitado
        productos_carrito::where('carrito_id', $guestCart->id)->delete();
        $guestCart->delete();

        return response()->json(['message' => 'Carrito combinado exitosamente'], 200);
    }

    public function getCartByUserId($userId)
    {
        try {
            $carrito = Carrito::where('user_id', $userId)->first();

            if (!$carrito) {
                return response()->json([
                    'message' => 'Carrito no encontrado',
                    'productos' => [],
                    'cantidad_total' => 0,
                    'total_precio' => 0
                ], 200); // Cambiar a 200 en lugar de 404
            }

            $productosCarrito = productos_carrito::with('producto')->where('carrito_id', $carrito->id)->get();

            $cantidadTotal = $productosCarrito->sum('cantidad');
            $totalPrecio = $productosCarrito->sum('total');

            return response()->json([
                'carrito_id' => $carrito->id,
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
                return response()->json([
                    'message' => 'Carrito no encontrado',
                    'productos' => [],
                    'cantidad_total' => 0,
                    'total_precio' => 0
                ], 200); // Cambiar a 200 en lugar de 404
            }

            $productosCarrito = productos_carrito::with('producto')->where('carrito_id', $carrito->id)->get();

            $cantidadTotal = $productosCarrito->sum('cantidad');
            $totalPrecio = $productosCarrito->sum('total');

            return response()->json([
                'carrito_id' => $carrito->id,
                'productos' => $productosCarrito,
                'cantidad_total' => $cantidadTotal,
                'total_precio' => $totalPrecio,
                'uuid' => $carrito->uuid,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error interno del servidor', 'error' => $e->getMessage()], 500);
        }
    }

    public function vaciarPorUserId(Request $request)
    {
        $userId = $request->input('user_id');

        if (!$userId) {
            return response()->json(['message' => 'user_id es requerido'], 400);
        }

        $carrito = Carrito::where('user_id', $userId)->first();

        if (!$carrito) {
            return response()->json(['message' => 'Carrito no encontrado para este usuario'], 404);
        }

        productos_carrito::where('carrito_id', $carrito->id)->delete();

        return response()->json(['message' => 'Carrito vaciado exitosamente'], 200);
    }
}
