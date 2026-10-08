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

        $user = $request->user();
        $commerce = $user?->tipo_usuario === 'comercio'
            ? \App\Models\Comercio::where('id_usuario', $user->id_usuario)->first()
            : null;
        if (!$commerce || !ctype_digit((string) $comercioId) || (int) $comercioId !== (int) $commerce->id_comercio) {
            return response()->json(['error' => 'No tienes acceso a este comercio.'], 403);
        }

        // 3. Guardamos el ID de forma global en la configuración para que los modelos lo usen
        $previousTenant = config('app.comercio_id');
        config(['app.comercio_id' => $commerce->id_comercio]);
        try {
            return $next($request);
        } finally {
            config(['app.comercio_id' => $previousTenant]);
        }
    }
}
