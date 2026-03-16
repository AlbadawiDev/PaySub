<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Reclamo;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReclamoController extends Controller
{
    private const TIPOS = [
        Reclamo::TIPO_RECLAMO,
        Reclamo::TIPO_SOLICITUD,
    ];

    private const ESTADOS = [
        Reclamo::ESTADO_ABIERTO,
        Reclamo::ESTADO_EN_REVISION,
        Reclamo::ESTADO_RESUELTO,
        Reclamo::ESTADO_CERRADO,
    ];

    private const PRIORIDADES = [
        Reclamo::PRIORIDAD_BAJA,
        Reclamo::PRIORIDAD_MEDIA,
        Reclamo::PRIORIDAD_ALTA,
    ];

    private const CATEGORIAS = [
        'general',
        'pagos',
        'suscripciones',
        'facturacion',
        'cuenta',
        'soporte_tecnico',
        'mejora',
        'otro',
    ];

    public function store(Request $request)
    {
        /** @var Usuario|null $usuario */
        $usuario = Auth::user();

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no autenticado.'], 401);
        }

        if ($usuario->tipo_usuario !== 'cliente') {
            return response()->json(['error' => 'Solo los clientes pueden crear reclamos o solicitudes.'], 403);
        }

        $validated = $request->validate([
            'tipo' => 'required|string|in:' . implode(',', self::TIPOS),
            'categoria' => 'required|string|in:' . implode(',', self::CATEGORIAS),
            'prioridad' => 'nullable|string|in:' . implode(',', self::PRIORIDADES),
            'asunto' => 'required|string|max:160',
            'descripcion' => 'required|string|min:20|max:3000',
            'id_suscripcion' => 'nullable|integer|exists:suscripciones,id_suscripcion',
            'id_pago' => 'nullable|integer|exists:pagos,id_pago',
        ]);

        $suscripcion = null;
        $pago = null;

        if (!empty($validated['id_suscripcion'])) {
            $suscripcion = Suscripcion::with('plan')
                ->where('id_suscripcion', $validated['id_suscripcion'])
                ->where('id_usuario', $usuario->id_usuario)
                ->first();

            if (!$suscripcion) {
                return response()->json(['error' => 'La suscripción indicada no pertenece al usuario autenticado.'], 403);
            }
        }

        if (!empty($validated['id_pago'])) {
            $pago = Pago::with('suscripcion.plan')
                ->where('id_pago', $validated['id_pago'])
                ->first();

            if (!$pago || (int) $pago->suscripcion?->id_usuario !== (int) $usuario->id_usuario) {
                return response()->json(['error' => 'El pago indicado no pertenece al usuario autenticado.'], 403);
            }

            if ($suscripcion && (int) $pago->id_suscripcion !== (int) $suscripcion->id_suscripcion) {
                return response()->json(['error' => 'El pago no corresponde a la suscripción indicada.'], 422);
            }

            $suscripcion ??= $pago->suscripcion;
        }

        $reclamo = Reclamo::create([
            'id_usuario' => $usuario->id_usuario,
            'id_comercio' => $suscripcion?->plan?->id_comercio,
            'id_suscripcion' => $suscripcion?->id_suscripcion,
            'id_pago' => $pago?->id_pago,
            'tipo' => $this->normalizeTipo($validated['tipo']),
            'categoria' => $validated['categoria'],
            'prioridad' => $validated['prioridad'] ?? Reclamo::PRIORIDAD_MEDIA,
            'estado' => Reclamo::ESTADO_ABIERTO,
            'asunto' => trim($validated['asunto']),
            'descripcion' => trim($validated['descripcion']),
        ]);

        $reclamo->update([
            'codigo' => $this->generateCode($reclamo->id_reclamo),
        ]);

        return response()->json([
            'mensaje' => 'Reclamo creado exitosamente.',
            'data' => $this->loadReclamo($reclamo),
        ], 201);
    }

    public function index(Request $request)
    {
        /** @var Usuario|null $usuario */
        $usuario = Auth::user();

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no autenticado.'], 401);
        }

        if (!in_array($usuario->tipo_usuario, ['cliente', 'administrador'], true)) {
            return response()->json(['error' => 'No tienes permisos para consultar reclamos.'], 403);
        }

        $query = Reclamo::query()
            ->with([
                'usuario:id_usuario,nombre,apellido,correo_electronico',
                'comercio:id_comercio,nombre_comercio',
                'suscripcion:id_suscripcion,id_plan,fecha_fin,estado',
                'suscripcion.plan:id_plan,id_comercio,nombre_plan',
                'pago:id_pago,id_suscripcion,monto,moneda,estatus_pago',
            ])
            ->orderByDesc('created_at');

        if ($usuario->tipo_usuario === 'cliente') {
            $query->where('id_usuario', $usuario->id_usuario);
        }

        if ($request->filled('estado')) {
            $estado = $this->normalizeEstado($request->query('estado'));
            if (!in_array($estado, self::ESTADOS, true)) {
                return response()->json(['error' => 'Filtro de estado inválido.'], 422);
            }

            $query->where('estado', $estado);
        }

        if ($request->filled('tipo')) {
            $tipo = $this->normalizeTipo($request->query('tipo'));
            if (!in_array($tipo, self::TIPOS, true)) {
                return response()->json(['error' => 'Filtro de tipo inválido.'], 422);
            }

            $query->where('tipo', $tipo);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('codigo', 'like', '%' . $search . '%')
                    ->orWhere('asunto', 'like', '%' . $search . '%')
                    ->orWhere('descripcion', 'like', '%' . $search . '%');
            });
        }

        return response()->json([
            'mensaje' => 'Reclamos recuperados exitosamente.',
            'data' => $query->get(),
        ], 200);
    }

    public function show(int $id)
    {
        /** @var Usuario|null $usuario */
        $usuario = Auth::user();

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no autenticado.'], 401);
        }

        $reclamo = Reclamo::with([
            'usuario:id_usuario,nombre,apellido,correo_electronico,telefono',
            'comercio:id_comercio,nombre_comercio,correo_contacto',
            'suscripcion:id_suscripcion,id_plan,fecha_inicio,fecha_fin,estado',
            'suscripcion.plan:id_plan,id_comercio,nombre_plan,precio,moneda,frecuencia',
            'pago:id_pago,id_suscripcion,monto,moneda,estatus_pago,referencia_operacion,created_at',
        ])->where('id_reclamo', $id)->first();

        if (!$reclamo) {
            return response()->json(['error' => 'Reclamo no encontrado.'], 404);
        }

        if ($usuario->tipo_usuario === 'cliente' && (int) $reclamo->id_usuario !== (int) $usuario->id_usuario) {
            return response()->json(['error' => 'No autorizado para ver este reclamo.'], 403);
        }

        if (!in_array($usuario->tipo_usuario, ['cliente', 'administrador'], true)) {
            return response()->json(['error' => 'No tienes permisos para consultar reclamos.'], 403);
        }

        return response()->json([
            'mensaje' => 'Reclamo recuperado exitosamente.',
            'data' => $reclamo,
        ], 200);
    }

    public function updateStatus(Request $request, int $id)
    {
        /** @var Usuario|null $usuario */
        $usuario = Auth::user();

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no autenticado.'], 401);
        }

        if ($usuario->tipo_usuario !== 'administrador') {
            return response()->json(['error' => 'Solo un administrador puede actualizar el estado de un reclamo.'], 403);
        }

        $validated = $request->validate([
            'estado' => 'required|string|in:' . implode(',', self::ESTADOS),
            'respuesta_admin' => 'nullable|string|max:3000',
        ]);

        $reclamo = Reclamo::where('id_reclamo', $id)->first();

        if (!$reclamo) {
            return response()->json(['error' => 'Reclamo no encontrado.'], 404);
        }

        $estado = $this->normalizeEstado($validated['estado']);

        $reclamo->estado = $estado;
        if (array_key_exists('respuesta_admin', $validated)) {
            $reclamo->respuesta_admin = $validated['respuesta_admin'] ? trim($validated['respuesta_admin']) : null;
        }
        $reclamo->resuelto_at = in_array($estado, [Reclamo::ESTADO_RESUELTO, Reclamo::ESTADO_CERRADO], true)
            ? now()
            : null;
        $reclamo->save();

        return response()->json([
            'mensaje' => 'Estado del reclamo actualizado exitosamente.',
            'data' => $this->loadReclamo($reclamo),
        ], 200);
    }

    private function loadReclamo(Reclamo $reclamo): Reclamo
    {
        return $reclamo->fresh([
            'usuario:id_usuario,nombre,apellido,correo_electronico',
            'comercio:id_comercio,nombre_comercio',
            'suscripcion:id_suscripcion,id_plan,fecha_fin,estado',
            'suscripcion.plan:id_plan,id_comercio,nombre_plan',
            'pago:id_pago,id_suscripcion,monto,moneda,estatus_pago',
        ]);
    }

    private function normalizeEstado(string $estado): string
    {
        return match (mb_strtolower(trim($estado))) {
            'abierto', 'abierta' => Reclamo::ESTADO_ABIERTO,
            'en_revision', 'revision', 'en revision' => Reclamo::ESTADO_EN_REVISION,
            'resuelto', 'resuelta' => Reclamo::ESTADO_RESUELTO,
            'cerrado', 'cerrada' => Reclamo::ESTADO_CERRADO,
            default => mb_strtolower(trim($estado)),
        };
    }

    private function normalizeTipo(string $tipo): string
    {
        return match (mb_strtolower(trim($tipo))) {
            'reclamo' => Reclamo::TIPO_RECLAMO,
            'solicitud' => Reclamo::TIPO_SOLICITUD,
            default => mb_strtolower(trim($tipo)),
        };
    }

    private function generateCode(int $id): string
    {
        return sprintf('RCL-%s-%04d', now()->format('Ymd'), $id);
    }
}
