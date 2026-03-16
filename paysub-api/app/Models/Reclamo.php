<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reclamo extends Model
{
    use HasFactory;

    public const TIPO_RECLAMO = 'reclamo';
    public const TIPO_SOLICITUD = 'solicitud';

    public const ESTADO_ABIERTO = 'abierto';
    public const ESTADO_EN_REVISION = 'en_revision';
    public const ESTADO_RESUELTO = 'resuelto';
    public const ESTADO_CERRADO = 'cerrado';

    public const PRIORIDAD_BAJA = 'baja';
    public const PRIORIDAD_MEDIA = 'media';
    public const PRIORIDAD_ALTA = 'alta';

    protected $table = 'reclamos';
    protected $primaryKey = 'id_reclamo';

    protected $fillable = [
        'id_usuario',
        'id_comercio',
        'id_suscripcion',
        'id_pago',
        'codigo',
        'tipo',
        'categoria',
        'prioridad',
        'estado',
        'asunto',
        'descripcion',
        'respuesta_admin',
        'resuelto_at',
    ];

    protected $casts = [
        'resuelto_at' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function comercio()
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }

    public function suscripcion()
    {
        return $this->belongsTo(Suscripcion::class, 'id_suscripcion', 'id_suscripcion');
    }

    public function pago()
    {
        return $this->belongsTo(Pago::class, 'id_pago', 'id_pago');
    }
}
