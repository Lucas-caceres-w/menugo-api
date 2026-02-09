<?php

// Listado de productos y categorías (para clientes)

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CategoriasController;
use App\Http\Controllers\LocalClosuresController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\PedidosController;
use App\Http\Controllers\ProductosController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LocalController;
use App\Http\Controllers\LocalSchedulesController;

Route::get('/locales/{localId}/categorias', [CategoriasController::class, 'index']);
Route::get('/categorias/{categoriaId}/productos', [ProductosController::class, 'index']);
Route::get('/productos/{productoId}', [ProductosController::class, 'show']);
Route::get('/local/{slug}', [LocalController::class, 'showClientLocal']);
// Crear pedido por cliente (no requiere auth, se puede pasar email/nombre en el request)
Route::post('/pedidos/{localId}', [PedidosController::class, 'store']);
// Mercado Pago webhooks (MP llama a esto)
Route::post('/mercadopago/webhook', [MercadoPagoController::class, 'webhooks']);
// Opcional: mostrar estado del pedido para cliente
Route::get('/pedidos/{pedidoId}', [PedidosController::class, 'showCliente']);
//Login & Register
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [RegisterController::class, 'register']);
//Get settings
Route::get('/mercadopago/{localId}/settings', [MercadoPagoController::class, 'settings']);

Route::middleware('auth:sanctum')->group(function () {
            // Usuario logueado
            Route::get('/user', [UserController::class, 'show']);
            Route::put('/user', [UserController::class, 'update']);
            Route::post('/user', [UserController::class, 'store']); // opcional, creación interna
            Route::post('/logout', [AuthController::class, 'logout']);

            // Locales del usuario
            Route::get('/locales', [LocalController::class, 'index']);
            Route::post('/locales/images/{localId}', [LocalController::class, 'saveImages']); // opcional, creación interna
            Route::delete('/locales/{local}/image/{type}', [LocalController::class, 'destroyImages']);
            Route::post('/locales', [LocalController::class, 'store']);
            Route::get('/locales/{localId}', [LocalController::class, 'show']);
            Route::put('/locales/{localId}', [LocalController::class, 'update']);
            Route::delete('/locales/{localId}', [LocalController::class, 'destroy']);

            //Horarios abiertos y cerrados locales
            Route::get('/locales/{localId}/schedules', [LocalSchedulesController::class, 'show']);
            Route::put('/locales/{localId}/schedules', [LocalSchedulesController::class, 'update']);
            Route::post('/locales/{localId}/closures', [LocalClosuresController::class, 'store']);
            Route::post('/locales/{localId}/closures/range', [LocalClosuresController::class, 'storeRange']);
            Route::delete('/locales/{localId}/closures/{closure}', [LocalClosuresController::class, 'destroy']);

            // Categorias de un local
            Route::get('/locales/{localId}/menu', [CategoriasController::class, 'show']);
            Route::post('/locales/{localId}/categorias', [CategoriasController::class, 'store']);
            Route::put('/categorias/{categoriaId}', [CategoriasController::class, 'update']);
            Route::delete('/categorias/{categoriaId}', [CategoriasController::class, 'destroy']);

            // Productos
            Route::post('/productos/{categoriaId}', [ProductosController::class, 'store']);
            Route::put('/productos/{productoId}', [ProductosController::class, 'update']);
            Route::delete('/productos/{productoId}', [ProductosController::class, 'destroy']);

            // Pedidos (para locales, ver todos los pedidos recibidos)
            Route::get('/pedidos/admin/{localId}', [PedidosController::class, 'index']);
            Route::put('/pedidos/{pedidoId}/estado', [PedidosController::class, 'cambiarEstado']); // pendiente, aprobado, cancelado, pagado

            // MercadoPago - vincular, refrescar token, preferencias
            Route::post('/mercadopago/oauth', [MercadoPagoController::class, 'oauth']);
            Route::post('/mercadopago/preference', [MercadoPagoController::class, 'createPreference']);
            Route::post('/mercadopago/save-preapproval', [MercadoPagoController::class, 'savePreapproval']);
            Route::delete('/mercadopago/token/{localId}', [MercadoPagoController::class, 'disconnect']);

            // Suscripciones (opcional)
            Route::post('/subscription', [MercadoPagoController::class, 'iniciarSubscripcion']);
            Route::post('/mercadopago/cambiar-plan', [MercadoPagoController::class, 'cambiar']);
});
