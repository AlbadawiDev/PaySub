<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SuscripcionController;
use App\Http\Controllers\ComercioController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\DatoPagoComercioController; // Importar el nuevo controlador

/*
|--------------------------------------------------------------------------
| API Routes - PaySub Project
|--------------------------------------------------------------------------
*/

// --- RUTAS PÚBLICAS ---
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/register-cliente', [AuthController::class, 'registerCliente']);
Route::post('/register/verify-otp', [AuthController::class, 'verifyRegistrationOtp']);
Route::post('/register/resend-otp', [AuthController::class, 'resendRegistrationOtp']);

// --- RUTAS PROTEGIDAS (Sanctum) ---

Route::middleware('auth:sanctum')->group(function () {
    
    // Perfil y Autenticación
    Route::get('/user', [AuthController::class, 'userProfile']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Directorio de Comercios
    Route::get('/comercios', [ComercioController::class, 'index']);
    Route::get('/comercios/{id}', [ComercioController::class, 'show']);
    Route::put('/comercio/perfil', [ComercioController::class, 'updateProfile']); // Ruta para el perfil del comercio
    Route::get('/comercio/reportes-pago', [ComercioController::class, 'reportesDePago']); // <--- NUEVA RUTA

    // Catálogo de Planes
    Route::get('/planes', [PlanController::class, 'index']);
    Route::get('/planes/{id}', [PlanController::class, 'show']);
    Route::get('/mis-planes', [PlanController::class, 'misPlanes']);

    // Gestión de Planes (Comercios)
    Route::post('/planes', [PlanController::class, 'store']);
    Route::put('/planes/{id}', [PlanController::class, 'update']);
    Route::delete('/planes/{id}', [PlanController::class, 'destroy']);

    // Suscripciones
    Route::post('/suscripciones', [SuscripcionController::class, 'store']);
    Route::get('/suscripciones', [SuscripcionController::class, 'index']);
    Route::get('/mis-suscripciones', [SuscripcionController::class, 'misSuscripciones']);
	Route::put('/suscripciones/{id}/estado', [SuscripcionController::class, 'cambiarEstado']);

    // Métodos de Pago (Clientes)
    Route::get('/metodos-pago', [MetodoPagoController::class, 'index']);
    Route::post('/metodos-pago', [MetodoPagoController::class, 'store']);
    Route::delete('/metodos-pago/{id}', [MetodoPagoController::class, 'destroy']);

    // Configuración de Cobro (Comercios)
    Route::post('/comercio/datos-pago', [DatoPagoComercioController::class, 'store']);
    Route::get('/comercio/datos-pago', [DatoPagoComercioController::class, 'showMyData']);

    // Historial de Pagos General
    Route::get('/pagos', [PagoController::class, 'index']); 
    Route::get('/pagos/{id}', [PagoController::class, 'show']);
	
});
