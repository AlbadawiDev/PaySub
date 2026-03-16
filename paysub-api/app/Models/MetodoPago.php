<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MetodoPago extends Model
{
    use HasFactory;

    protected $table = 'metodos_pago';
    protected $primaryKey = 'id_metodo_pago';

    protected $fillable = [
        'id_usuario',
        'tipo_metodo',
        'proveedor',
        'token_pasarela',
        'marca_tarjeta',
        'ultimos_cuatro',
        'mes_expiracion',
        'anio_expiracion',
        'banco',
        'telefono_pago',
        'cedula_titular',
        'es_predeterminado'
    ];

    // Relación: Un método de pago pertenece a un usuario
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}