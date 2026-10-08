<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PagoController extends Controller
{
    public function comprobante($id)
    {
        $authorization = $this->show($id);
        if ($authorization->getStatusCode() !== 200) {
            return $authorization;
        }

        $payment = Pago::findOrFail($id);
        if (!$payment->comprobante_path || !Storage::disk('local')->exists($payment->comprobante_path)) {
            return response()->json(['error' => 'Comprobante no encontrado.'], 404);
        }

        return Storage::disk('local')->download($payment->comprobante_path);
    }

    public function updateStatus(Request $request, $id)
    {
        if (!in_array(Auth::user()->tipo_usuario, ['comercio', 'administrador'], true)) {
            return response()->json(['error' => 'Solo el comercio receptor o un administrador puede revisar pagos.'], 403);
        }
        $authorization = $this->show($id);
        if ($authorization->getStatusCode() !== 200) {
            return $authorization;
        }
        $validated = $request->validate(['estatus_pago' => 'required|in:completado,fallido']);

        return DB::transaction(function () use ($id, $validated) {
            $payment = Pago::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($payment->tipo_flujo !== 'manual' || $payment->estatus_pago !== 'en_revision') {
                return response()->json(['error' => 'Este pago no está pendiente de revisión manual.'], 409);
            }
            $subscription = $payment->suscripcion()->lockForUpdate()->firstOrFail();
            if ($subscription->estado !== 'pendiente') {
                return response()->json(['error' => 'La suscripción ya no está pendiente.'], 409);
            }
            $payment->update($validated);
            $subscription->update(['estado' => $validated['estatus_pago'] === 'completado' ? 'activa' : 'cancelada']);

            return response()->json(['mensaje' => 'Pago revisado exitosamente.', 'data' => $payment->fresh('suscripcion')]);
        });
    }

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
