<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Suscripcion extends Model
{
    use HasFactory;

    protected $table = 'suscripciones';
    protected $primaryKey = 'id_suscripcion';

    protected $fillable = [
        'id_usuario',
        'id_plan',
        'fecha_inicio',
        'fecha_fin',
        'estado'
    ];

    // Relación: Una suscripción pertenece a un Usuario
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    // Relación: Una suscripción pertenece a un Plan
    public function plan()
    {
        return $this->belongsTo(Plan::class, 'id_plan', 'id_plan');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'id_suscripcion', 'id_suscripcion');
    }

    public function reclamos()
    {
        return $this->hasMany(Reclamo::class, 'id_suscripcion', 'id_suscripcion');
    }
}
