<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\Plan;
use App\Models\Suscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PlanController extends Controller
{
    public function index()
    {
        $planes = Plan::with('comercio:id_comercio,nombre_comercio')->get();

        return response()->json([
            'mensaje' => 'Planes recuperados exitosamente',
            'data' => $planes,
        ], 200);
    }

    public function store(Request $request)
    {
        $usuario = Auth::user();

        if (!$usuario || $usuario->tipo_usuario !== 'comercio') {
            return response()->json(['error' => 'Solo los comercios pueden crear planes.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nombre_plan' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:1000',
            'precio' => 'required|numeric|min:0',
            'frecuencia' => 'required|in:semanal,mensual,trimestral,anual',
            'moneda' => 'required|string|size:3',
            'modalidad_cobro' => 'required|in:prepago,postpago',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comercio = Comercio::where('id_usuario', $usuario->id_usuario)->first();
        if (!$comercio) {
            return response()->json(['error' => 'No se encontró un comercio para este usuario.'], 404);
        }

        $plan = Plan::create([
            'id_comercio' => $comercio->id_comercio,
            'nombre_plan' => $request->nombre_plan,
            'descripcion' => $request->descripcion ?? '',
            'precio' => $request->precio,
            'frecuencia' => $request->frecuencia,
            'moneda' => strtoupper($request->moneda),
            'modalidad_cobro' => $request->modalidad_cobro,
            'estado' => true,
        ]);

        return response()->json([
            'mensaje' => 'Plan creado exitosamente',
            'data' => $plan,
        ], 201);
    }

    public function show($id)
    {
        $plan = Plan::with('comercio')->find($id);

        if (!$plan) {
            return response()->json(['error' => 'Plan no encontrado'], 404);
        }

        return response()->json([
            'mensaje' => 'Plan recuperado exitosamente',
            'data' => $plan,
        ], 200);
    }

    public function misPlanes()
    {
        $usuario = Auth::user();

        if (!$usuario || $usuario->tipo_usuario !== 'comercio') {
            return response()->json(['error' => 'Solo los comercios pueden consultar sus planes.'], 403);
        }

        $comercio = Comercio::where('id_usuario', $usuario->id_usuario)->first();
        if (!$comercio) {
            return response()->json(['error' => 'No eres dueño de un comercio'], 403);
        }

        $planes = Plan::where('id_comercio', $comercio->id_comercio)->orderByDesc('created_at')->get();

        return response()->json([
            'mensaje' => 'Planes del comercio recuperados',
            'data' => $planes,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $usuario = Auth::user();

        if (!$usuario || $usuario->tipo_usuario !== 'comercio') {
            return response()->json(['error' => 'Solo los comercios pueden actualizar planes.'], 403);
        }

        $comercio = Comercio::where('id_usuario', $usuario->id_usuario)->first();
        if (!$comercio) {
            return response()->json(['error' => 'No se encontró un comercio asociado.'], 404);
        }

        $plan = Plan::where('id_plan', $id)->where('id_comercio', $comercio->id_comercio)->first();
        if (!$plan) {
            return response()->json(['error' => 'Plan no encontrado o no autorizado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre_plan' => 'sometimes|required|string|max:100',
            'descripcion' => 'nullable|string|max:1000',
            'precio' => 'sometimes|required|numeric|min:0',
            'frecuencia' => 'sometimes|required|in:semanal,mensual,trimestral,anual',
            'moneda' => 'sometimes|required|string|size:3',
            'modalidad_cobro' => 'sometimes|required|in:prepago,postpago',
            'estado' => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payload = $request->only([
            'nombre_plan',
            'descripcion',
            'precio',
            'frecuencia',
            'moneda',
            'modalidad_cobro',
            'estado',
        ]);

        if (isset($payload['moneda'])) {
            $payload['moneda'] = strtoupper($payload['moneda']);
        }

        $plan->update($payload);

        return response()->json([
            'mensaje' => 'Plan actualizado exitosamente',
            'data' => $plan->fresh(),
        ], 200);
    }

    public function destroy($id)
    {
        $usuario = Auth::user();

        if (!$usuario || $usuario->tipo_usuario !== 'comercio') {
            return response()->json(['error' => 'Solo los comercios pueden desactivar planes.'], 403);
        }

        $comercio = Comercio::where('id_usuario', $usuario->id_usuario)->first();
        if (!$comercio) {
            return response()->json(['error' => 'No se encontró un comercio asociado.'], 404);
        }

        $plan = Plan::where('id_plan', $id)->where('id_comercio', $comercio->id_comercio)->first();
        if (!$plan) {
            return response()->json(['error' => 'Plan no encontrado o no autorizado.'], 404);
        }

        $hasActiveSubscriptions = Suscripcion::where('id_plan', $plan->id_plan)
            ->where('estado', 'activa')
            ->exists();

        if ($hasActiveSubscriptions) {
            $plan->update(['estado' => false]);

            return response()->json([
                'mensaje' => 'El plan tiene suscripciones activas. Fue desactivado para evitar nuevas compras.',
                'data' => $plan,
            ], 200);
        }

        $plan->delete();

        return response()->json([
            'mensaje' => 'Plan eliminado exitosamente',
            'data' => null,
        ], 200);
    }
}
