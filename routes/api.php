<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController ;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController as Categoria;   
use App\Http\Controllers\ProductosCarritoController;
use App\Http\Controllers\ProveedorController as Proveedor;
use App\Http\Controllers\ClienteController as Cliente;
use App\Http\Controllers\PersonalSistemaController as Apoyo;
use App\Http\Controllers\DeliveryController as Delivery;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ProductoController as Producto;
use App\Http\Controllers\PedidoController as Pedido;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/productos', [Producto::class, 'index']);
Route::get('/categoria-productos', [Categoria::class, 'todasLasCategoriasConProductos']);

Route::middleware('auth:sanctum')->group(function () {
    // Estas rutas requieren autenticación
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
    //LOGIN
    Route::apiResource('/v1/cliente', Cliente::class);
    Route::put('/v1/cliente/{id}', [Cliente::class, 'update']);
    Route::apiResource('/v1/users', UserController::class);
    Route::apiResource('/v1/proveedor', Proveedor::class);
    Route::apiResource('/v1/apoyo', Apoyo::class);
    Route::apiResource('/v1/delivery', Delivery::class);
    //VENTA

    //PRODUCTO
    // Route::get('/productos', [Producto::class, 'index']);
    Route::get('/productos/{id}', [Producto::class, 'show']);
    Route::post('/productos', [Producto::class, 'store']);
    Route::post('/producto/{id}', [Producto::class, 'update']);
    Route::delete('/productos/{id}', [Producto::class, 'destroy']);
    Route::post('/compra-peso/{id}', [Producto::class, 'compra_peso']);
    Route::post('/compra-unidad/{id}', [Producto::class, 'compra_unidad']);


    Route::apiResource('/v1/categorias', Categoria::class);
    Route::put('/categorias/{id}', [Categoria::class, 'update']);

    //COMPRA
    Route::post('/pedidos', [Pedido::class, 'store']);

    //CARRITO
    Route::get('/carrito', [ProductosCarritoController::class, 'index']); // Mostrar el carrito
    Route::post('/carrito/agregar', [ProductosCarritoController::class, 'agregar']); // Agregar producto al carrito
    Route::put('/carrito/{id}', [ProductosCarritoController::class, 'actualizar']); // Actualizar cantidad de un producto en el carrito
    Route::delete('/carrito/{id}', [ProductosCarritoController::class, 'eliminar']); // Eliminar producto del carrito
    Route::delete('/carrito/vaciar', [ProductosCarritoController::class, 'vaciar']); // Vaciar carrito

    //Delivery
    Route::put('/modificar-estado-pedido', [DeliveryController::class, 'updatePedidoEstado']);

    
});

