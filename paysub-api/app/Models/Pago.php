<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';
    protected $primaryKey = 'id_pago';

    protected $fillable = [
        'id_suscripcion',
        'id_metodo_pago',
        'monto',
        'moneda',
        'estatus_pago',
        'tipo_flujo',
        'referencia_operacion',
        'banco_remitente',
        'telefono_remitente',
        'fecha_pago',
        'comprobante_path'
    ];

    public function suscripcion()
    {
        return $this->belongsTo(Suscripcion::class, 'id_suscripcion', 'id_suscripcion');
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'id_metodo_pago', 'id_metodo_pago');
    }

    public function reclamos()
    {
        return $this->hasMany(Reclamo::class, 'id_pago', 'id_pago');
    }
}
