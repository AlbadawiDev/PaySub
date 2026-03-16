<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comercio extends Model
{
    protected $table = 'comercios';
    protected $primaryKey = 'id_comercio';

    protected $fillable = [
        'id_usuario',
        'nombre_comercio',
        'logo',
        'descripcion',
        'rif_identificacion',
        'correo_contacto',
        'sitio_web',
        'redes_sociales'
    ];

    protected $casts = [
        'redes_sociales' => 'array',
        'fecha_afiliacion' => 'datetime'
    ];

    /**
     * Relación: Un comercio tiene muchos datos de pago (ej. varios bancos)
     */
    public function datosPago()
    {
        return $this->hasMany(DatoPagoComercio::class, 'id_comercio', 'id_comercio');
    }

    public function planes()
    {
        return $this->hasMany(Plan::class, 'id_comercio', 'id_comercio');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function reclamos()
    {
        return $this->hasMany(Reclamo::class, 'id_comercio', 'id_comercio');
    }
}
