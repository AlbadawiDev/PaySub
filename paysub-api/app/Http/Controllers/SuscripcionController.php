<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SuscripcionController extends Controller
{
    private const ESTADO_ACTIVA = 'activa';
    private const ESTADO_CANCELADA = 'cancelada';

    public function store(Request $request)
    {
        $request->validate([
            'id_plan' => 'required|exists:planes,id_plan',
            'metodo_usado' => 'required|in:tarjeta,pago_movil',
            'id_metodo_pago' => 'required_if:metodo_usado,tarjeta|exists:metodos_pago,id_metodo_pago',
            'referencia_operacion' => 'required_if:metodo_usado,pago_movil|string|max:100',
            'banco_remitente' => 'required_if:metodo_usado,pago_movil|string|max:100',
            'telefono_remitente' => 'required_if:metodo_usado,pago_movil|string|max:20',
            'fecha_pago' => 'required_if:metodo_usado,pago_movil|date',
            'comprobante' => 'required_if:metodo_usado,pago_movil|image|max:2048',
        ]);

        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user || $user->tipo_usuario !== 'cliente') {
            return response()->json(['error' => 'Solo los clientes pueden comprar suscripciones.'], 403);
        }

        $plan = Plan::where('id_plan', $request->id_plan)->where('estado', true)->first();
        if (!$plan) {
            return response()->json(['error' => 'El plan no está disponible.'], 422);
        }

        if ($request->metodo_usado === 'tarjeta') {
            $metodoPago = MetodoPago::where('id_metodo_pago', $request->id_metodo_pago)
                ->where('id_usuario', $user->id_usuario)
                ->first();

            if (!$metodoPago) {
                return response()->json(['error' => 'El método de pago no pertenece al usuario autenticado.'], 403);
            }
        }

        $suscripcionExistente = Suscripcion::where('id_usuario', $user->id_usuario)
            ->where('id_plan', $request->id_plan)
            ->where('estado', self::ESTADO_ACTIVA)
            ->where('fecha_fin', '>', Carbon::now())
            ->first();

        if ($suscripcionExistente) {
            return response()->json([
                'error' => 'Ya posees una suscripción activa para este plan que vence el ' . Carbon::parse($suscripcionExistente->fecha_fin)->format('d-m-Y'),
            ], 422);
        }

        $fechaInicio = Carbon::now();
        $fechaFin = $this->calcularFechaFin($plan, $fechaInicio);

        return DB::transaction(function () use ($request, $user, $plan, $fechaInicio, $fechaFin) {
            $suscripcion = Suscripcion::create([
                'id_usuario' => $user->id_usuario,
                'id_plan' => $plan->id_plan,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'estado' => self::ESTADO_ACTIVA,
            ]);

            if ($plan->modalidad_cobro === 'prepago') {
                $pagoData = [
                    'id_suscripcion' => $suscripcion->id_suscripcion,
                    'monto' => $plan->precio,
                    'moneda' => $plan->moneda,
                    'estatus_pago' => 'completado',
                    'tipo_flujo' => $request->metodo_usado === 'tarjeta' ? 'automatico' : 'manual',
                ];

                if ($request->metodo_usado === 'tarjeta') {
                    $pagoData['id_metodo_pago'] = $request->id_metodo_pago;
                    $pagoData['referencia_operacion'] = 'SIM-STRIPE-' . strtoupper(uniqid());
                } else {
                    if ($request->hasFile('comprobante')) {
                        $path = $request->file('comprobante')->store('comprobantes', 'public');
                        $pagoData['comprobante_path'] = $path;
                    }

                    $pagoData['referencia_operacion'] = $request->referencia_operacion;
                    $pagoData['banco_remitente'] = $request->banco_remitente;
                    $pagoData['telefono_remitente'] = $request->telefono_remitente;
                    $pagoData['fecha_pago'] = $request->fecha_pago;
                }

                Pago::create($pagoData);
            }

            return response()->json([
                'mensaje' => 'Suscripción procesada exitosamente',
                'data' => $suscripcion->load('plan'),
            ], 201);
        });
    }

    private function calcularFechaFin($plan, $fechaInicio)
    {
        return match ($plan->frecuencia) {
            'semanal' => $fechaInicio->copy()->addWeek(),
            'mensual' => $fechaInicio->copy()->addMonth(),
            'trimestral' => $fechaInicio->copy()->addMonths(3),
            'anual' => $fechaInicio->copy()->addYear(),
            default => $fechaInicio->copy()->addMonth(),
        };
    }

    public function index()
    {
        /** @var Usuario $usuario */
        $usuario = Auth::user();

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $query = Suscripcion::with(['plan.comercio', 'usuario'])->orderByDesc('created_at');

        $estado = request()->query('estado');
        if ($estado) {
            $normalized = $this->normalizeStatus($estado);
            if (!in_array($normalized, [self::ESTADO_ACTIVA, self::ESTADO_CANCELADA], true)) {
                return response()->json(['error' => 'Filtro de estado inválido.'], 422);
            }

            $query->where('estado', $normalized);
        }

        if ($usuario->tipo_usuario === 'cliente') {
            $query->where('id_usuario', $usuario->id_usuario);
        } elseif ($usuario->tipo_usuario === 'comercio') {
            $comercio = Comercio::where('id_usuario', $usuario->id_usuario)->first();

            if (!$comercio) {
                return response()->json(['error' => 'Acceso denegado. No eres un comercio.'], 403);
            }

            $query->whereHas('plan', function ($planQuery) use ($comercio) {
                $planQuery->where('id_comercio', $comercio->id_comercio);
            });
        } elseif ($usuario->tipo_usuario !== 'administrador') {
            return response()->json(['error' => 'No tienes permisos para consultar suscripciones.'], 403);
        }

        return response()->json([
            'mensaje' => 'Suscripciones recuperadas exitosamente',
            'data' => $query->get(),
        ], 200);
    }

    public function misSuscripciones()
    {
        /** @var Usuario $usuario */
        $usuario = Auth::user();

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        if ($usuario->tipo_usuario !== 'cliente') {
            return response()->json(['error' => 'Solo los clientes pueden consultar sus suscripciones.'], 403);
        }

        $suscripciones = Suscripcion::with('plan.comercio')
            ->where('id_usuario', $usuario->id_usuario)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'mensaje' => 'Suscripciones del cliente recuperadas',
            'data' => $suscripciones,
        ], 200);
    }

    public function cambiarEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|string',
        ]);

        /** @var Usuario $usuario */
        $usuario = Auth::user();

        $suscripcion = Suscripcion::with('plan')->where('id_suscripcion', $id)->first();

        if (!$suscripcion) {
            return response()->json(['message' => 'Suscripcion no encontrada'], 404);
        }

        $estadoNormalizado = $this->normalizeStatus($request->estado);
        if (!in_array($estadoNormalizado, [self::ESTADO_ACTIVA, self::ESTADO_CANCELADA], true)) {
            return response()->json(['error' => 'Estado inválido. Usa activa o cancelada.'], 422);
        }

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no autenticado.'], 401);
        }

        if ($usuario->tipo_usuario === 'cliente') {
            if ((int) $suscripcion->id_usuario !== (int) $usuario->id_usuario) {
                return response()->json(['error' => 'No autorizado para esta suscripción.'], 403);
            }

            if ($estadoNormalizado !== self::ESTADO_CANCELADA) {
                return response()->json(['error' => 'Un cliente solo puede cancelar una suscripción.'], 422);
            }
        } elseif ($usuario->tipo_usuario === 'comercio') {
            $comercio = Comercio::where('id_usuario', $usuario->id_usuario)->first();

            if (!$comercio || (int) $suscripcion->plan?->id_comercio !== (int) $comercio->id_comercio) {
                return response()->json(['error' => 'No autorizado para esta suscripción.'], 403);
            }
        } elseif ($usuario->tipo_usuario !== 'administrador') {
            return response()->json(['error' => 'No autorizado para esta operación.'], 403);
        }

        if ($suscripcion->estado === $estadoNormalizado) {
            return response()->json([
                'message' => 'La suscripción ya tiene ese estado.',
                'data' => $suscripcion,
            ], 200);
        }

        $suscripcion->estado = $estadoNormalizado;
        $suscripcion->save();

        return response()->json([
            'message' => 'Estado actualizado con exito',
            'data' => $suscripcion->fresh(['plan', 'usuario']),
        ], 200);
    }

    private function normalizeStatus(string $estado): string
    {
        $value = mb_strtolower(trim($estado));

        return match ($value) {
            'activa', 'activo' => self::ESTADO_ACTIVA,
            'cancelada', 'cancelado', 'inactiva', 'inactivo' => self::ESTADO_CANCELADA,
            default => $value,
        };
    }
}
