<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Maneja una solicitud entrante.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Buscamos el ID del comercio en la cabecera de la petición
        $comercioId = $request->header('X-Comercio-ID');

        // 2. Si no viene el ID, bloqueamos el acceso por seguridad
        if (!$comercioId) {
            return response()->json(['error' => 'No se especificó el ID del Comercio'], 403);
        }

        // 3. Guardamos el ID de forma global en la configuración para que los modelos lo usen
        config(['app.comercio_id' => $comercioId]);

        return $next($request);
    }
}