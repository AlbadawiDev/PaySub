<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use App\Models\Plan;
use App\Models\Usuario;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SuscripcionController extends Controller
{
    /**
     * Crear una nueva suscripción (Comprar un plan)
     */
    public function store(Request $request)
    {
        // 1. Validar entrada
        $request->validate([
            'id_plan'              => 'required|exists:planes,id_plan',
            'metodo_usado'         => 'required|in:tarjeta,pago_movil',
            'id_metodo_pago'       => 'required_if:metodo_usado,tarjeta|exists:metodos_pago,id_metodo_pago',
            'referencia_operacion' => 'required_if:metodo_usado,pago_movil',
            'banco_remitente'      => 'required_if:metodo_usado,pago_movil',
            'telefono_remitente'   => 'required_if:metodo_usado,pago_movil',
            'fecha_pago'           => 'required_if:metodo_usado,pago_movil|date',
            'comprobante'          => 'required_if:metodo_usado,pago_movil|image|max:2048', 
        ]);

        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user || $user->tipo_usuario !== 'cliente') {
            return response()->json(['error' => 'Solo los clientes pueden comprar suscripciones.'], 403);
        }

        // 2. NUEVA REGLA DE NEGOCIO: 
        // Solo bloqueamos si ya tiene UNA suscripción ACTIVA para el MISMO plan.
        $suscripcionExistente = Suscripcion::where('id_usuario', $user->id_usuario)
            ->where('id_plan', $request->id_plan) // Filtro por el plan que intenta comprar
            ->where('estado', 'activa')
            ->where('fecha_fin', '>', Carbon::now())
            ->first();

        if ($suscripcionExistente) {
            return response()->json([
                'error' => 'Ya posees una suscripción activa para este plan específico que vence el ' . Carbon::parse($suscripcionExistente->fecha_fin)->format('d-m-Y')
            ], 422);
        }

        $plan = Plan::findOrFail($request->id_plan);
        $fechaInicio = Carbon::now();
        $fechaFin = $this->calcularFechaFin($plan, $fechaInicio);

        try {
            return DB::transaction(function () use ($request, $user, $plan, $fechaInicio, $fechaFin) {
                
                // 3. Crear la Suscripción
                $suscripcion = Suscripcion::create([
                    'id_usuario'   => $user->id_usuario,
                    'id_plan'      => $plan->id_plan,
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin'    => $fechaFin,
                    'estado'       => 'activa' 
                ]);

                // 4. Registrar el pago si es Prepago
                if ($plan->modalidad_cobro === 'prepago') {
                    $pagoData = [
                        'id_suscripcion' => $suscripcion->id_suscripcion,
                        'monto'           => $plan->precio,
                        'moneda'          => $plan->moneda,
                        'estatus_pago'   => 'completado',
                        'tipo_flujo'     => $request->metodo_usado === 'tarjeta' ? 'automatico' : 'manual',
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
                        $pagoData['banco_remitente']      = $request->banco_remitente;
                        $pagoData['telefono_remitente']   = $request->telefono_remitente;
                        $pagoData['fecha_pago']           = $request->fecha_pago;
                    }

                    Pago::create($pagoData);
                }

                return response()->json([
                    'mensaje' => 'Suscripción procesada exitosamente',
                    'data'    => $suscripcion
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al procesar compra', 'detalle' => $e->getMessage()], 500);
        }
    }

    private function calcularFechaFin($plan, $fechaInicio)
    {
        return match ($plan->frecuencia) {
            'semanal'    => $fechaInicio->copy()->addWeek(),
            'mensual'    => $fechaInicio->copy()->addMonth(),
            'trimestral' => $fechaInicio->copy()->addMonths(3),
            'anual'      => $fechaInicio->copy()->addYear(),
            default      => $fechaInicio->copy()->addMonth(),
        };
    }

    public function index()
    {
        return response()->json(Suscripcion::with(['plan', 'usuario'])->get(), 200);
    }

    public function misSuscripciones()
    {
        $suscripciones = Suscripcion::with('plan')
            ->where('id_usuario', Auth::id())
            ->get();

        return response()->json($suscripciones, 200);
    }

    public function cambiarEstado(Request $request, $id)
    {
        $suscripcion = Suscripcion::where('id_suscripcion', $id)->first();

        if (!$suscripcion) {
            return response()->json(['message' => 'Suscripcion no encontrada'], 404);
        }

        $suscripcion->estado = $request->estado;
        $suscripcion->save();

        return response()->json([
            'message' => 'Estado actualizado con exito', 
            'suscripcion' => $suscripcion
        ], 200);
    }
}