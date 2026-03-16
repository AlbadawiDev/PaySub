<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class MetodoPagoController extends Controller
{
    /**
     * Listar métodos de pago del usuario autenticado
     */
    public function index()
    {
        $metodos = MetodoPago::where('id_usuario', Auth::id())->get();
        return response()->json($metodos);
    }

    /**
     * Registrar un nuevo método de pago (Tarjeta o Pago Móvil)
     */
public function store(Request $request)
{
    // Ahora solo validamos campos de tarjeta/pasarela
    $validator = Validator::make($request->all(), [
        'tipo_metodo'       => 'required|in:tarjeta,paypal',
        'token_pasarela'    => 'required|string|unique:metodos_pago,token_pasarela',
        'marca_tarjeta'     => 'nullable|string',
        'ultimos_cuatro'    => 'nullable|string|size:4',
        'mes_expiracion'    => 'nullable|integer|between:1,12',
        'anio_expiracion'   => 'nullable|integer|min:' . date('Y'),
        'es_predeterminado' => 'boolean',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
        return DB::transaction(function () use ($request) {
            $userId = Auth::id();

            // Si es predeterminado, reseteamos los otros
            if ($request->es_predeterminado) {
                MetodoPago::where('id_usuario', $userId)->update(['es_predeterminado' => false]);
            }

            $metodo = MetodoPago::create([
                'id_usuario'        => $userId,
                'tipo_metodo'       => $request->tipo_metodo,
                'proveedor'         => 'stripe', // Por defecto
                'token_pasarela'    => $request->token_pasarela,
                'marca_tarjeta'     => $request->marca_tarjeta,
                'ultimos_cuatro'    => $request->ultimos_cuatro,
                'mes_expiracion'    => $request->mes_expiracion,
                'anio_expiracion'   => $request->anio_expiracion,
                'es_predeterminado' => $request->es_predeterminado ?? false,
            ]);

            return response()->json([
                'mensaje' => 'Tarjeta registrada exitosamente',
                'metodo'  => $metodo
            ], 201);
        });
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error al registrar tarjeta: ' . $e->getMessage()], 500);
    }
}

    /**
     * Eliminar un método de pago
     */
    public function destroy($id)
    {
        $metodo = MetodoPago::where('id_usuario', Auth::id())->find($id);

        if (!$metodo) {
            return response()->json(['mensaje' => 'Método de pago no encontrado'], 404);
        }

        $metodo->delete();
        return response()->json(['mensaje' => 'Método de pago eliminado']);
    }
}