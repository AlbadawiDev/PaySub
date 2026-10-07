<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->estado) {
            return response()->json(['mensaje' => 'La cuenta está deshabilitada.', 'code' => 'ACCOUNT_DISABLED'], 403);
        }

        return $next($request);
    }
}
