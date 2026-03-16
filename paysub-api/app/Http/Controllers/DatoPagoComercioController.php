<?php

namespace App\Http\Controllers;

use App\Models\DatoPagoComercio;
use App\Models\Comercio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DatoPagoComercioController extends Controller
{
    /**
     * Guardar o actualizar los datos de cobro del comercio
     */
    public function store(Request $request)
    {
        $usuario = Auth::user();

        // Verificar que sea un comercio
        if ($usuario->tipo_usuario !== 'comercio') {
            return response()->json(['error' => 'Solo los comercios pueden registrar datos de cobro'], 403);
        }

        $comercio = Comercio::where('id_usuario', $usuario->id_usuario)->first();

        $validator = Validator::make($request->all(), [
            'banco'         => 'required|string|max:100',
            'telefono_pago' => 'required|string|max:20',
            'rif_cedula'    => 'required|string|max:20',
            'titular'       => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dato = DatoPagoComercio::updateOrCreate(
            ['id_comercio' => $comercio->id_comercio], // Si ya existe uno, lo actualiza
            [
                'banco'         => $request->banco,
                'telefono_pago' => $request->telefono_pago,
                'rif_cedula'    => $request->rif_cedula,
                'titular'       => $request->titular,
                'activo'        => true
            ]
        );

        return response()->json([
            'mensaje' => 'Datos de cobro actualizados exitosamente',
            'datos'   => $dato
        ], 200);
    }

    /**
     * Obtener los datos de cobro del comercio autenticado
     */
    public function showMyData()
    {
        $comercio = Comercio::where('id_usuario', Auth::id())->first();
        $datos = DatoPagoComercio::where('id_comercio', $comercio->id_comercio)->first();
        
        return response()->json($datos);
    }
}