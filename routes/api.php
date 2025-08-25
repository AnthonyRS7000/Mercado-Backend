<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController as Categoria;
use App\Http\Controllers\ProductosCarritoController;
use App\Http\Controllers\ProveedorController as Proveedor;
use App\Http\Controllers\ClienteController as Cliente;
use App\Http\Controllers\PersonalSistemaController as Apoyo;
use App\Http\Controllers\DeliveryController as Delivery;
use App\Http\Controllers\ProductoController as Producto;
use App\Http\Controllers\PedidoController as Pedido;
use App\Http\Controllers\EntregaController as Entregar;
use App\Http\Controllers\PedidoProgramadoController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\SolicitudRegistroController;
use App\Http\Controllers\ImagenController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ------------------------
// Rutas públicas (sin auth)
// ------------------------
Route::post('/login', [AuthController::class, 'login']);
Route::get('/productos', [Producto::class, 'index']);
Route::post('/v1/cliente', [Cliente::class, 'store']);
Route::post('/solicitudes', [SolicitudRegistroController::class, 'store']);

// Webhook de Mercado Pago (debe ser público)
Route::post('/mp/webhook', [MercadoPagoController::class, 'webhook']);

// Productos / Categorías
Route::get('/categoria-productos', [Categoria::class, 'todasLasCategoriasConProductos']);
Route::post('/calcular-precio/{id}', [Producto::class, 'calcularPrecio']);
Route::get('/productos-uno/{id}', [Producto::class, 'mostrar_one']);
Route::get('/productos-proveedor/{id}', [Producto::class, 'productosPorProveedor']);
Route::get('/imagenes', [ImagenController::class, 'index']);
Route::post('/imagenes', [ImagenController::class, 'store']);

// Google OAuth
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
Route::post('/auth/google-login', [GoogleAuthController::class, 'handleGoogleLogin']);

// Categorías
Route::apiResource('/v1/categorias', Categoria::class);

// =============================
// 🔹 Carrito público (Invitado)
// =============================
Route::get('/carrito', [ProductosCarritoController::class, 'index']); // listar por uuid
Route::post('/carrito/invitado/agregar', [ProductosCarritoController::class, 'agregarInvitado']);
Route::post('/carrito/invitado/vaciar', [ProductosCarritoController::class, 'vaciarPorUuid']);
Route::get('/carrito/uuid/{uuid}', [ProductosCarritoController::class, 'getCartByUuid']);
Route::patch('/carrito-incrementar/{carritoId}/{productoId}', [ProductosCarritoController::class, 'incrementar'])
    ->middleware('throttle:60,1');

// actualizar / eliminar productos del carrito (comparten lógica)
Route::put('/carrito-actualizar/{carritoId}/{productoId}', [ProductosCarritoController::class, 'actualizar']);
Route::delete('/carrito-eliminar/{carritoId}/{productoId}', [ProductosCarritoController::class, 'eliminar']);

// ------------------------
// Rutas protegidas con auth
// ------------------------
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // LOGIN / LOGOUT
    Route::put('/v1/cliente/{id}', [Cliente::class, 'update']);
    Route::apiResource('/v1/users', UserController::class);
    Route::apiResource('/v1/proveedor', Proveedor::class);
    Route::apiResource('/v1/apoyo', Apoyo::class);
    Route::apiResource('/v1/delivery', Delivery::class);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ADMIN
    Route::get('/solicitudes', [SolicitudRegistroController::class, 'index']);
    Route::put('/solicitudes/aprobar/{id}', [SolicitudRegistroController::class, 'aprobar']);
    Route::put('/solicitudes/rechazar/{id}', [SolicitudRegistroController::class, 'rechazar']);

    // PRODUCTOS
    Route::get('/productos/{id}', [Producto::class, 'show']);
    Route::post('/productos', [Producto::class, 'store']);
    Route::post('/actualizarelproducto/{id}', [Producto::class, 'update']);
    Route::delete('/productos/{id}', [Producto::class, 'destroy']);
    Route::post('/compra-peso/{id}', [Producto::class, 'compra_peso']);
    Route::post('/compra-unidad/{id}', [Producto::class, 'compra_unidad']);
    Route::get('/productos-categoria/{id}', [Producto::class, 'productosPorCategoria']);
    Route::put('/productos/desactivar/{id}', [Producto::class, 'desactivar']);
    Route::put('/productos/activar/{id}', [Producto::class, 'activar']);

    // MERCADO PAGO
    Route::post('/mercadopago/preferencia', [MercadoPagoController::class, 'crearPreferencia']);

    // CATEGORÍAS
    Route::put('/categorias/{id}', [Categoria::class, 'update']);

    // PEDIDOS
    Route::post('/pedidos', [Pedido::class, 'store']);
    Route::get('/pedidos/ultimo/{userId}', [Pedido::class, 'getLastPedido']);
    Route::get('/pedidos/{userId}', [Pedido::class, 'getPedidosByUserId']);
    Route::post('/pedido-programado', [PedidoProgramadoController::class, 'store']);

    // =============================
    // 🔹 Carrito para usuario logueado
    // =============================
    Route::post('/carrito/user/agregar', [ProductosCarritoController::class, 'agregarUser']);
    Route::post('/carrito/user/vaciar', [ProductosCarritoController::class, 'vaciarPorUserId']);
    Route::get('/carrito/user/{userId}', [ProductosCarritoController::class, 'getCartByUserId']);

    // Merge carrito invitado -> user
    Route::post('/carrito/merge', [ProductosCarritoController::class, 'mergeCart']);

    // DELIVERY
    Route::put('/modificar-estado-pedido', [Delivery::class, 'updatePedidoEstado']);
    Route::get('delivery/pedidos/pendientes', [Delivery::class, 'getPedidosPendientes']);
    Route::get('delivery/pedidos/pedidos-delivery', [Pedido::class, 'pedidosParaRecoger']);
    Route::put('/pedidos/aceptar/{pedidoId}', [Pedido::class, 'aceptarPedidoDelivery']);
    Route::put('/pedidos/en-ruta/{pedidoId}', [Pedido::class, 'actualizarEstadoEnRuta']);
    Route::put('/pedidos/cancelar/{pedidoId}', [Pedido::class, 'cancelarPedidoDelivery']);
    Route::get('/pedidos/{pedido_id}/{delivery_id}', [Pedido::class, 'getPedidoById']);
    Route::get('/delivery/pedido-activo/{deliveryId}', [Pedido::class, 'getPedidoActivo']);
    Route::post('/entregas', [Entregar::class, 'store']);

    // CLIENTE
    Route::get('/pedidos/cliente/{id}', [Cliente::class, 'getPedidosByUserId']);
    Route::get('/v1/cliente', [Cliente::class, 'index']);
    Route::get('/v1/cliente/{id}', [Cliente::class, 'show']);
    Route::put('/v1/cliente/{id}', [Cliente::class, 'update']);
    Route::delete('/v1/cliente/{id}', [Cliente::class, 'destroy']);

    // PROVEEDOR
    Route::get('/proveedor/{id}', [Proveedor::class, 'proveedorPorId']);
    Route::get('/proveedor/pedidos/{id}', [Entregar::class, 'pedidosPorProveedor']);
    Route::put('/pedidos/notificar-recolector/{pedido_id}', [Entregar::class, 'notificarRecolector']);
    Route::get('/proveedores/categorias/{id}', [Proveedor::class, 'categoriasPorProveedor']);

    // PERSONAL DE SISTEMA (recolector)
    Route::get('apoyo/pedidos/notificados', [Apoyo::class, 'pedidosNotificados']);
    Route::put('/pedidos/marcar-listo/{id}', [Apoyo::class, 'marcarProductoListo']);
    Route::get('apoyo/pedidos/listos', [Apoyo::class, 'pedidosListosParaRecoger']);
    Route::get('/pedidos/listosParaEnviar', [Pedido::class, 'getPedidosListosParaEnviar']);
    Route::put('/pedidos/llamardelivery/{id}', [Pedido::class, 'LLamarDelivery']);
    Route::get('/pedidos-por-confirmar', [Apoyo::class, 'pedidosPorConfirmar']);
    Route::put('/confirmar-pedido/{id}', [Apoyo::class, 'confirmarPedido']);
});
