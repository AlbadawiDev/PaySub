<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatoPagoComercio extends Model
{
    protected $table = 'datos_pago_comercio';
    protected $primaryKey = 'id_dato_pago';

    protected $fillable = [
        'id_comercio',
        'banco',
        'telefono_pago',
        'rif_cedula',
        'titular',
        'activo'
    ];

    public function comercio()
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }
}