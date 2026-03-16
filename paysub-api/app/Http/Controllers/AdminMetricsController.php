<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\Pago;
use App\Models\Reclamo;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminMetricsController extends Controller
{
    public function index(Request $request)
    {
        /** @var Usuario|null $usuario */
        $usuario = Auth::user();

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no autenticado.'], 401);
        }

        if ($usuario->tipo_usuario !== 'administrador') {
            return response()->json(['error' => 'Solo un administrador puede consultar métricas globales.'], 403);
        }

        $usuariosTotal = Usuario::count();
        $clientesTotal = Usuario::where('tipo_usuario', 'cliente')->count();
        $comerciosTotal = Usuario::where('tipo_usuario', 'comercio')->count();
        $suscripcionesActivas = Suscripcion::whereRaw('LOWER(estado) = ?', ['activa'])->count();
        $pagosPendientes = Pago::whereIn('estatus_pago', ['pendiente', 'en_revision'])->count();
        $ingresosTotales = (float) Pago::where('estatus_pago', 'completado')->sum('monto');
        $ingresosMesActual = (float) Pago::where('estatus_pago', 'completado')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('monto');
        $reclamosAbiertos = Reclamo::whereIn('estado', [Reclamo::ESTADO_ABIERTO, Reclamo::ESTADO_EN_REVISION])->count();
        $reclamosResueltos = Reclamo::whereIn('estado', [Reclamo::ESTADO_RESUELTO, Reclamo::ESTADO_CERRADO])->count();
        $reclamosTotal = Reclamo::count();

        $reclamosPorEstado = Reclamo::query()
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->orderBy('estado')
            ->get();

        $pagosPorEstado = Pago::query()
            ->select('estatus_pago', DB::raw('COUNT(*) as total'))
            ->groupBy('estatus_pago')
            ->orderBy('estatus_pago')
            ->get()
            ->map(fn ($item) => [
                'estado' => $item->estatus_pago,
                'total' => (int) $item->total,
            ]);

        $registrosUltimosSieteDias = collect(range(6, 0, -1))
            ->map(function (int $offset) {
                $date = now()->subDays($offset)->startOfDay();

                return [
                    'fecha' => $date->toDateString(),
                    'usuarios' => Usuario::whereDate('created_at', $date)->count(),
                    'reclamos' => Reclamo::whereDate('created_at', $date)->count(),
                    'pagos' => Pago::whereDate('created_at', $date)->count(),
                ];
            })
            ->values();

        $ultimosReclamos = Reclamo::with([
            'usuario:id_usuario,nombre,apellido,correo_electronico',
            'comercio:id_comercio,nombre_comercio',
        ])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $ultimosPagos = Pago::with([
            'suscripcion:id_suscripcion,id_usuario,id_plan',
            'suscripcion.usuario:id_usuario,nombre,apellido',
            'suscripcion.plan:id_plan,id_comercio,nombre_plan',
            'suscripcion.plan.comercio:id_comercio,nombre_comercio',
        ])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $topComercios = Comercio::query()
            ->leftJoin('planes', 'planes.id_comercio', '=', 'comercios.id_comercio')
            ->leftJoin('suscripciones', 'suscripciones.id_plan', '=', 'planes.id_plan')
            ->leftJoin('pagos', function ($join) {
                $join->on('pagos.id_suscripcion', '=', 'suscripciones.id_suscripcion')
                    ->where('pagos.estatus_pago', '=', 'completado');
            })
            ->select(
                'comercios.id_comercio',
                'comercios.nombre_comercio',
                DB::raw('COALESCE(SUM(pagos.monto), 0) as ingresos_totales'),
                DB::raw('COUNT(DISTINCT suscripciones.id_suscripcion) as suscripciones_total')
            )
            ->groupBy('comercios.id_comercio', 'comercios.nombre_comercio')
            ->orderByDesc('ingresos_totales')
            ->limit(5)
            ->get();

        return response()->json([
            'mensaje' => 'Métricas globales recuperadas exitosamente.',
            'data' => [
                'overview' => [
                    'usuarios_total' => $usuariosTotal,
                    'clientes_total' => $clientesTotal,
                    'comercios_total' => $comerciosTotal,
                    'suscripciones_activas' => $suscripcionesActivas,
                    'pagos_pendientes' => $pagosPendientes,
                    'ingresos_totales' => $ingresosTotales,
                    'ingresos_mes_actual' => $ingresosMesActual,
                    'reclamos_total' => $reclamosTotal,
                    'reclamos_abiertos' => $reclamosAbiertos,
                    'reclamos_resueltos' => $reclamosResueltos,
                    'tasa_resolucion_reclamos' => $reclamosTotal > 0
                        ? round(($reclamosResueltos / $reclamosTotal) * 100, 2)
                        : 0,
                ],
                'reclamos_por_estado' => $reclamosPorEstado,
                'pagos_por_estado' => $pagosPorEstado,
                'registros_ultimos_7_dias' => $registrosUltimosSieteDias,
                'ultimos_reclamos' => $ultimosReclamos,
                'ultimos_pagos' => $ultimosPagos,
                'top_comercios' => $topComercios,
            ],
        ], 200);
    }
}
