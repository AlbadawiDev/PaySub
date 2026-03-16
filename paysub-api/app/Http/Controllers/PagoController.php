<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\Pago;
use Illuminate\Support\Facades\Auth;

class PagoController extends Controller
{
    public function index()
    {
        $usuario = Auth::user();

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no autenticado.'], 401);
        }

        $query = Pago::with(['suscripcion.plan.comercio', 'suscripcion.usuario', 'metodoPago'])
            ->orderByDesc('created_at');

        if ($usuario->tipo_usuario === 'cliente') {
            $query->whereHas('suscripcion', function ($subQuery) use ($usuario) {
                $subQuery->where('id_usuario', $usuario->id_usuario);
            });
        } elseif ($usuario->tipo_usuario === 'comercio') {
            $comercio = Comercio::where('id_usuario', $usuario->id_usuario)->first();
            if (!$comercio) {
                return response()->json(['error' => 'No existe un comercio asociado a este usuario.'], 404);
            }

            $query->whereHas('suscripcion.plan', function ($planQuery) use ($comercio) {
                $planQuery->where('id_comercio', $comercio->id_comercio);
            });
        } elseif ($usuario->tipo_usuario !== 'administrador') {
            return response()->json(['error' => 'No tienes permisos para consultar pagos.'], 403);
        }

        return response()->json([
            'mensaje' => 'Pagos recuperados exitosamente',
            'data' => $query->get(),
        ], 200);
    }

    public function show($id)
    {
        $usuario = Auth::user();

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no autenticado.'], 401);
        }

        $pago = Pago::with(['suscripcion.plan.comercio', 'suscripcion.usuario', 'metodoPago'])->find($id);

        if (!$pago) {
            return response()->json(['error' => 'Pago no encontrado.'], 404);
        }

        if ($usuario->tipo_usuario === 'cliente') {
            if ((int) $pago->suscripcion?->id_usuario !== (int) $usuario->id_usuario) {
                return response()->json(['error' => 'No autorizado para ver este pago.'], 403);
            }
        } elseif ($usuario->tipo_usuario === 'comercio') {
            $comercio = Comercio::where('id_usuario', $usuario->id_usuario)->first();
            $pagoComercioId = $pago->suscripcion?->plan?->id_comercio;

            if (!$comercio || (int) $pagoComercioId !== (int) $comercio->id_comercio) {
                return response()->json(['error' => 'No autorizado para ver este pago.'], 403);
            }
        } elseif ($usuario->tipo_usuario !== 'administrador') {
            return response()->json(['error' => 'No tienes permisos para consultar pagos.'], 403);
        }

        return response()->json([
            'mensaje' => 'Pago recuperado exitosamente',
            'data' => $pago,
        ], 200);
    }
}
