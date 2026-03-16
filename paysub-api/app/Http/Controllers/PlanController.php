<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Comercio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PlanController extends Controller
{
    /**
     * LISTAR PLANES (Público)
     */
    public function index()
    {
        // Traemos la modalidad de cobro también para que el Front sepa cómo mostrar el botón de compra
        $planes = Plan::with('comercio:id_comercio,nombre_comercio')->get();
        
        return response()->json($planes, 200);
    }

    /**
     * CREAR PLAN (Privado - Solo Comercios)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_plan'     => 'required|string|max:100',
            'precio'          => 'required|numeric|min:0',
            'frecuencia'      => 'required|string',
            'moneda'          => 'required|string|max:3',
            'modalidad_cobro' => 'required|in:prepago,postpago', // Validación para el nuevo campo
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comercio = Comercio::where('id_usuario', Auth::id())->first();

        if (!$comercio) {
            return response()->json(['error' => 'No se encontró un comercio para este usuario'], 404);
        }

        try {
            $plan = Plan::create([
                'id_comercio'     => $comercio->id_comercio,
                'nombre_plan'     => $request->nombre_plan,
                'descripcion'     => $request->descripcion ?? '',
                'precio'          => $request->precio,
                'frecuencia'      => $request->frecuencia,
                'moneda'          => $request->moneda,
                'modalidad_cobro' => $request->modalidad_cobro, // Guardamos la modalidad
                'estado'          => true
            ]);

            return response()->json([
                'mensaje' => 'Plan creado exitosamente',
                'plan'    => $plan
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al guardar el plan',
                'detalle_tecnico' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * MOSTRAR UN PLAN ESPECÍFICO
     */
    public function show($id)
    {
        $plan = Plan::with('comercio')->find($id);

        if (!$plan) {
            return response()->json(['error' => 'Plan no encontrado'], 404);
        }

        return response()->json($plan, 200);
    }

    /**
     * LISTAR PLANES DEL COMERCIO LOGUEADO
     */
    public function misPlanes()
    {
        $comercio = Comercio::where('id_usuario', Auth::id())->first();

        if (!$comercio) {
            return response()->json(['error' => 'No eres dueño de un comercio'], 403);
        }

        $planes = Plan::where('id_comercio', $comercio->id_comercio)->get();
        return response()->json($planes, 200);
    }
}