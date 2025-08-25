<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\productos_carrito;
use App\Models\Producto;
use App\Models\Carrito;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class ProductosCarritoController extends Controller
{
    // =============================
    // LISTAR CARRITO POR UUID (invitado)
    // =============================
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

        $cantidadTotal = $productosCarrito->sum('cantidad');
        $totalPrecio   = $productosCarrito->sum('total');

        return response()->json([
            'productos'      => $productosCarrito,
            'cantidad_total' => $cantidadTotal,
            'total_precio'   => $totalPrecio,
            'uuid'           => $carrito->uuid
        ], 200);
    }

    // =============================
    // INVITADO - agregar producto (uuid obligatorio)
    // =============================
    public function agregarInvitado(Request $request)
    {
        $request->validate([
            'uuid'        => 'required|uuid',
            'producto_id' => 'required|exists:productos,id',
            'cantidad'    => 'required|numeric|min:0.01'
        ]);

        $carrito = Carrito::firstOrCreate(['uuid' => $request->uuid], ['uuid' => $request->uuid]);
        return $this->agregarProducto($carrito, $request);
    }

    // =============================
    // USUARIO - agregar producto (token o user_id)
    // =============================
    public function agregarUser(Request $request)
    {
        $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'cantidad'    => 'required|numeric|min:0.01',
        ]);

        $user_id = Auth::id() ?: $request->input('user_id');
        if (!$user_id) {
            return response()->json(['message' => 'Usuario no identificado'], 400);
        }

        $carrito = Carrito::firstOrCreate(
            ['user_id' => $user_id],
            ['uuid' => (string) Str::uuid()]
        );

        return $this->agregarProducto($carrito, $request);
    }

    // =============================
    // MÉTODO REUTILIZADO
    // =============================
    private function agregarProducto(Carrito $carrito, Request $request)
    {
        $producto = Producto::find($request->producto_id);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $item = productos_carrito::where('carrito_id', $carrito->id)
            ->where('producto_id', $request->producto_id)
            ->first();

        if ($item) {
            $item->cantidad += $request->cantidad;
            $item->total    = $producto->precio * $item->cantidad;
            $item->fecha_agrego = now();
            $item->save();
            $mensaje = "Cantidad actualizada en producto existente";
        } else {
            $item = productos_carrito::create([
                'carrito_id'   => $carrito->id,
                'producto_id'  => $request->producto_id,
                'cantidad'     => $request->cantidad,
                'fecha_agrego' => now(),
                'total'        => $producto->precio * $request->cantidad,
                'estado'       => 1
            ]);
            $mensaje = "Producto agregado al carrito";
        }

        return response()->json([
            'success'  => true,
            'message'  => $mensaje,
            'producto' => $item,
            'carrito'  => [
                'id'      => $carrito->id,
                'uuid'    => $carrito->uuid,
                'user_id' => $carrito->user_id
            ]
        ], 200);
    }

    // =============================
    // ACTUALIZAR PRODUCTO DEL CARRITO
    // =============================
    public function actualizar(Request $request, $carritoId, $productoId)
    {
        $request->validate([
            'cantidad' => 'required|numeric|min:0.01',
        ]);

        $item = productos_carrito::where('carrito_id', $carritoId)
            ->where('producto_id', $productoId)
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Producto no encontrado en el carrito'], 404);
        }

        $producto = Producto::find($productoId);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $item->update([
            'cantidad' => $request->cantidad,
            'total'    => $producto->precio * $request->cantidad
        ]);

        return response()->json(['message' => 'Cantidad actualizada', 'carrito' => $item], 200);
    }

    public function incrementar(Request $request, $carritoId, $productoId)
    {
        $validated = $request->validate([
            'delta' => 'required|integer', // 👈 ya no 'in:-1,1'
        ]);

        return DB::transaction(function () use ($carritoId, $productoId, $validated) {
            $item = productos_carrito::with('producto')
                ->where('carrito_id', $carritoId)
                ->where('producto_id', $productoId)
                ->lockForUpdate()
                ->first();

            if (!$item) {
                return response()->json(['message' => 'Producto no encontrado en el carrito'], 404);
            }

            $producto = Producto::find($productoId);
            if (!$producto) {
                return response()->json(['message' => 'Producto no encontrado'], 404);
            }

            $cantidadActual = (int) $item->cantidad;
            $nuevaCantidad = $cantidadActual + $validated['delta'];

            // Para productos por unidad: mínimo 1
            if ($item->producto && $item->producto->tipo === 'unidad') {
                $nuevaCantidad = max(1, $nuevaCantidad);
            }

            $item->cantidad = $nuevaCantidad;
            $item->total = $producto->precio * $nuevaCantidad;
            $item->fecha_agrego = now();
            $item->save();

            $sum = productos_carrito::where('carrito_id', $carritoId)
                ->selectRaw('SUM(cantidad) as cantidad_total, SUM(total) as total_precio')
                ->first();

            return response()->json([
                'message' => 'Cantidad actualizada',
                'item' => [
                    'id' => $item->id,
                    'producto_id' => $item->producto_id,
                    'cantidad' => $item->cantidad,
                    'producto' => $item->producto,
                    'total' => $item->total,
                ],
                'resumen' => [
                    'cantidad_total' => (float) ($sum->cantidad_total ?? 0),
                    'total_precio'   => (float) ($sum->total_precio ?? 0),
                ],
            ], 200);
        });
    }



    // =============================
    // ELIMINAR PRODUCTO DEL CARRITO
    // =============================
    public function eliminar($carritoId, $productoId)
    {
        $item = productos_carrito::where('carrito_id', $carritoId)
            ->where('producto_id', $productoId)
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Producto no encontrado en el carrito'], 404);
        }

        $item->delete();
        return response()->json(['message' => 'Producto eliminado del carrito'], 200);
    }

    // =============================
    // VACIAR CARRITO POR UUID (invitado)
    // =============================
    public function vaciarPorUuid(Request $request)
    {
        $uuid = $request->query('uuid');
        if (!$uuid) {
            return response()->json(['message' => 'UUID es requerido'], 400);
        }

        $carrito = Carrito::where('uuid', $uuid)->first();
        if (!$carrito) {
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }

        productos_carrito::where('carrito_id', $carrito->id)->delete();
        return response()->json(['message' => 'Carrito vaciado (invitado)'], 200);
    }

    // =============================
    // VACIAR CARRITO POR USER (logueado)
    // =============================
    public function vaciarPorUserId(Request $request)
    {
        $userId = Auth::id() ?: $request->input('user_id');
        if (!$userId) {
            return response()->json(['message' => 'user_id es requerido'], 400);
        }

        $carrito = Carrito::where('user_id', $userId)->first();
        if (!$carrito) {
            return response()->json(['message' => 'Carrito no encontrado para este usuario'], 404);
        }

        productos_carrito::where('carrito_id', $carrito->id)->delete();
        return response()->json(['message' => 'Carrito vaciado (usuario)'], 200);
    }

    // =============================
    // MERGE: pasar de invitado a user
    // =============================
    public function mergeCart(Request $request)
    {
        $request->validate([
            'uuid'    => 'required|uuid',
            'user_id' => 'required|exists:users,id'
        ]);

        $guestCart = Carrito::where('uuid', $request->uuid)->first();
        $userCart  = Carrito::firstOrCreate(['user_id' => $request->user_id], ['uuid' => (string) Str::uuid()]);

        if (!$guestCart) {
            return response()->json(['message' => 'Carrito de invitado no encontrado'], 404);
        }

        $guestProducts = productos_carrito::where('carrito_id', $guestCart->id)->get();

        foreach ($guestProducts as $guestProduct) {
            $existing = productos_carrito::where('carrito_id', $userCart->id)
                ->where('producto_id', $guestProduct->producto_id)
                ->first();

            if ($existing) {
                $existing->cantidad += $guestProduct->cantidad;
                $existing->total += $guestProduct->total;
                $existing->save();
            } else {
                productos_carrito::create([
                    'carrito_id'  => $userCart->id,
                    'producto_id' => $guestProduct->producto_id,
                    'cantidad'    => $guestProduct->cantidad,
                    'total'       => $guestProduct->total,
                    'fecha_agrego'=> now(),
                    'estado'      => 1,
                ]);
            }
        }

        // limpiar carrito invitado
        productos_carrito::where('carrito_id', $guestCart->id)->delete();
        $guestCart->delete();

        return response()->json(['message' => 'Carrito combinado exitosamente'], 200);
    }

    // =============================
    // GET POR USER
    // =============================
    public function getCartByUserId($userId)
    {
        $carrito = Carrito::where('user_id', $userId)->first();

        if (!$carrito) {
            return response()->json([
                'message'        => 'Carrito no encontrado',
                'productos'      => [],
                'cantidad_total' => 0,
                'total_precio'   => 0
            ], 200);
        }

        $productosCarrito = productos_carrito::with('producto')->where('carrito_id', $carrito->id)->get();
        return response()->json([
            'carrito_id'     => $carrito->id,
            'productos'      => $productosCarrito,
            'cantidad_total' => $productosCarrito->sum('cantidad'),
            'total_precio'   => $productosCarrito->sum('total'),
            'uuid'           => $carrito->uuid,
        ], 200);
    }

    // =============================
    // GET POR UUID
    // =============================
    public function getCartByUuid($uuid)
    {
        $carrito = Carrito::where('uuid', $uuid)->first();

        if (!$carrito) {
            return response()->json([
                'message'        => 'Carrito no encontrado',
                'productos'      => [],
                'cantidad_total' => 0,
                'total_precio'   => 0
            ], 200);
        }

        $productosCarrito = productos_carrito::with('producto')->where('carrito_id', $carrito->id)->get();
        return response()->json([
            'carrito_id'     => $carrito->id,
            'productos'      => $productosCarrito,
            'cantidad_total' => $productosCarrito->sum('cantidad'),
            'total_precio'   => $productosCarrito->sum('total'),
            'uuid'           => $carrito->uuid,
        ], 200);
    }
}
