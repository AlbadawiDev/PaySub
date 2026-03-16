<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__.'/../routes/api.php', 
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 1. Mantenemos tus alias existentes
        $middleware->alias([
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
        ]);

        // 2. Configuración de seguridad para la API (CORS y CSRF)
        $middleware->validateCsrfTokens(except: [
            'api/*', // Importante: Las APIs no usan tokens de sesión CSRF
        ]);

        // 3. (Opcional) Si necesitas habilitar CORS global de forma manual
        // Laravel 11 lo maneja automáticamente si detecta peticiones API, 
        // pero esta línea asegura que el middleware esté presente.
        $middleware->statefulApi(); 
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();