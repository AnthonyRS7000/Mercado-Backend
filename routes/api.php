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
use App\Http\Controllers\EntregaController as Entregar;
use App\Http\Controllers\PedidoProgramadoController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\SolicitudRegistroController;


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

// Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/productos', [Producto::class, 'index']);
Route::post('/carrito/vaciar', [ProductosCarritoController::class, 'vaciarPorUserId']);
Route::post('/v1/cliente', [Cliente::class, 'store']);
Route::post('/solicitudes', [SolicitudRegistroController::class, 'store']);
Route::post('/mp/webhook', [MercadoPagoController::class, 'webhook']);

//Productos
Route::get('/categoria-productos', [Categoria::class, 'todasLasCategoriasConProductos']);
Route::post('/calcular-precio/{id}', [Producto::class, 'calcularPrecio']);
Route::get('/productos-uno/{id}', [Producto::class, 'mostrar_one']);
Route::get('/productos-proveedor/{id}', [Producto::class, 'productosPorProveedor']);

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
Route::post('/auth/google-login', [GoogleAuthController::class, 'handleGoogleLogin']);

//categoria
Route::apiResource('/v1/categorias', Categoria::class);

//carrito
Route::get('/carrito', [ProductosCarritoController::class, 'index']);
Route::post('/carrito/agregar', [ProductosCarritoController::class, 'agregar']);
Route::put('/carrito-actualizar/{carritoId}/{productoId}', [ProductosCarritoController::class, 'actualizar']);
Route::delete('/carrito-eliminar/{carritoId}/{productoId}', [ProductosCarritoController::class, 'eliminar']);
Route::post('/carrito/vaciar', [ProductosCarritoController::class, 'vaciar']);
Route::post('/carrito/transferir', [ProductosCarritoController::class, 'transferirCarrito']);
Route::get('/carrito/uuid/{uuid}', [ProductosCarritoController::class, 'getCartByUuid']);

Route::middleware('auth:sanctum')->group(function () {
    // Estas rutas requieren autenticación
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
    //LOGIN
    Route::put('/v1/cliente/{id}', [Cliente::class, 'update']);
    Route::apiResource('/v1/users', UserController::class);
    Route::apiResource('/v1/proveedor', Proveedor::class);
    Route::apiResource('/v1/apoyo', Apoyo::class);
    Route::apiResource('/v1/delivery', Delivery::class);
    Route::post('/logout', [AuthController::class, 'logout']);

    //ADMIN
        Route::get('/solicitudes', [SolicitudRegistroController::class, 'index']); // Ver solicitudes
    Route::put('/solicitudes/aprobar/{id}', [SolicitudRegistroController::class, 'aprobar']); // Aprobar
    Route::put('/solicitudes/rechazar/{id}', [SolicitudRegistroController::class, 'rechazar']);

    //VENTA

    //PRODUCTO
    // Route::get('/productos', [Producto::class, 'index']);
    Route::get('/productos/{id}', [Producto::class, 'show']);
    Route::post('/productos', [Producto::class, 'store']);
    Route::post('/producto/{id}', [Producto::class, 'update']);
    Route::delete('/productos/{id}', [Producto::class, 'destroy']);
    Route::post('/compra-peso/{id}', [Producto::class, 'compra_peso']);
    Route::post('/compra-unidad/{id}', [Producto::class, 'compra_unidad']);
    Route::get('/productos-categoria/{id}', [Producto::class, 'productosPorCategoria']);

    //Mercado pago
    Route::post('/mercadopago/preferencia', [MercadoPagoController::class, 'crearPreferencia']);


    Route::put('/categorias/{id}', [Categoria::class, 'update']);

    //COMPRA
    Route::post('/pedidos', [Pedido::class, 'store']);

    //CARRITO
    Route::post('/carrito/merge', [ProductosCarritoController::class, 'mergeCart']);
    Route::get('/carrito/user/{userId}', [ProductosCarritoController::class, 'getCartByUserId']);

    // Route::get('/carrito', [ProductosCarritoController::class, 'index']); // Mostrar el carrito
    // Route::post('/carrito/agregar', [ProductosCarritoController::class, 'agregar']); // Agregar producto al carrito
    // Route::put('/carrito/{id}', [ProductosCarritoController::class, 'actualizar']); // Actualizar cantidad de un producto en el carrito
    // Route::delete('/carrito/{id}', [ProductosCarritoController::class, 'eliminar']); // Eliminar producto del carrito
    // Route::delete('/carrito/vaciar', [ProductosCarritoController::class, 'vaciar']); // Vaciar carrito

    //Delivery
    Route::put('/modificar-estado-pedido', [DeliveryController::class, 'updatePedidoEstado']);
    Route::get('/pedidos/pendientes', [DeliveryController::class, 'getPedidosPendientes']);
    Route::get('/pedidos/pedidos-delivery', [Pedido::class, 'pedidosParaRecoger']);
    Route::put('/pedidos/aceptar/{pedidoId}', [Pedido::class, 'aceptarPedidoDelivery']);
    Route::put('/pedidos/en-ruta/{pedidoId}', [Pedido::class, 'actualizarEstadoEnRuta']);
    Route::put('/pedidos/cancelar/{pedidoId}', [Pedido::class, 'cancelarPedidoDelivery']);
    Route::get('/pedidos/{pedido_id}/{delivery_id}', [Pedido::class, 'getPedidoById']);
    Route::get('/delivery/pedido-activo/{deliveryId}', [Pedido::class, 'getPedidoActivo']);
    Route::post('/entregas', [Entregar::class, 'store']);


    //Cliente
    Route::get('/pedidos/cliente/{id}', [Cliente::class, 'getPedidosByUserId']);
    Route::get('/v1/cliente', [Cliente::class, 'index']);
    Route::get('/v1/cliente/{id}', [Cliente::class, 'show']);
    Route::put('/v1/cliente/{id}', [Cliente::class, 'update']);
    Route::delete('/v1/cliente/{id}', [Cliente::class, 'destroy']);

    //Proveedor
    Route::get('/proveedor/{id}', [Proveedor::class, 'proveedorPorId']);
    Route::get('/proveedor/pedidos/{id}', [Entregar::class, 'pedidosPorProveedor']);
    Route::put('/pedidos/notificar-recolector/{pedido_id}', [Entregar::class, 'notificarRecolector']);
    Route::get('/proveedores/categorias/{id}', [Proveedor::class, 'categoriasPorProveedor']);


    //Personal_sistema(recolector)
    Route::get('/pedidos/notificados', [Apoyo::class, 'pedidosNotificados']);
    Route::put('/pedidos/marcar-listo/{id}', [Apoyo::class, 'marcarProductoListo']);
    Route::get('/pedidos/listos', [Apoyo::class, 'pedidosListosParaRecoger']);
    Route::get('/pedidos/listosParaEnviar', [Pedido::class, 'getPedidosListosParaEnviar']);
    Route::put('/pedidos/llamardelivery/{id}', [Pedido::class, 'LLamarDelivery']);
    Route::get('/pedidos-por-confirmar', [Apoyo::class, 'pedidosPorConfirmar']);
    Route::put('/confirmar-pedido/{id}', [Apoyo::class, 'confirmarPedido']);


    //pedido
    Route::get('/pedidos/ultimo/{userId}', [Pedido::class, 'getLastPedido']);
    Route::get('/pedidos/{userId}', [Pedido::class, 'getPedidosByUserId']);
    Route::post('/pedido-programado', [PedidoProgramadoController::class, 'store']);
});


