<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Agregamos esta importación

class ComercioController extends Controller
{
    /**
     * Listar todos los comercios registrados
     */
    public function index()
    {
        try {
            $comercios = Comercio::withCount('planes')->get();
            return response()->json([
                'mensaje' => 'Comercios recuperados exitosamente',
                'data' => $comercios
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al recuperar comercios',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver el detalle de un comercio específico y sus planes
     */
    public function show($id)
    {
        $comercio = Comercio::with('planes')->find($id);
        if (!$comercio) {
            return response()->json(['error' => 'Comercio no encontrado'], 404);
        }
        return response()->json($comercio, 200);
    }

    /**
     * ACTUALIZAR PERFIL: Logo, Descripción y Redes Sociales
     */
    public function updateProfile(Request $request)
    {
        try {
            // Usamos Auth::user() para mayor claridad
            $comercio = Comercio::where('id_usuario', Auth::user()->id_usuario)->first();

            if (!$comercio) {
                return response()->json(['error' => 'No se encontró un comercio vinculado'], 404);
            }

            $request->validate([
                'nombre_comercio' => 'string|max:150',
                'logo'            => 'nullable|string',
                'descripcion'     => 'nullable|string',
                'sitio_web'       => 'nullable|url',
                'redes_sociales'  => 'nullable|array'
            ]);

            $comercio->update($request->all());
            return response()->json(['mensaje' => 'Perfil actualizado', 'data' => $comercio], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar', 'detalle' => $e->getMessage()], 500);
        }
    }

    /**
     * VER REPORTES DE PAGO RECIBIDOS (Solo para el comercio logueado)
     */
    public function reportesDePago()
    {
        $idUsuario = Auth::id();
        $comercio = Comercio::where('id_usuario', $idUsuario)->first();

        if (!$comercio) {
            return response()->json(['error' => 'Acceso denegado. No eres un comercio.'], 403);
        }

        // Buscamos pagos de planes que pertenecen a este comercio
        $reportes = Pago::whereHas('suscripcion.plan', function ($query) use ($comercio) {
            $query->where('id_comercio', $comercio->id_comercio);
        })
        // CAMBIO AQUÍ: Traemos la relación completa para evitar el error de columna inexistente
        ->with(['suscripcion.usuario', 'suscripcion.plan'])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($pago) {
            if ($pago->comprobante_path) {
                $pago->url_comprobante = asset('storage/' . $pago->comprobante_path);
            }
            return $pago;
        });

        return response()->json([
            'mensaje' => 'Reportes de pago obtenidos',
            'data' => $reportes
        ], 200);
    }
}