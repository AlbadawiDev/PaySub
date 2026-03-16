<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $table = 'planes';
    protected $primaryKey = 'id_plan';

    protected $fillable = [
        'id_comercio',
        'nombre_plan',
        'descripcion',
        'precio',
        'frecuencia',
        'moneda',
        'modalidad_cobro', // Nuevo campo
        'estado'
    ];

    public function comercio()
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class, 'id_plan', 'id_plan');
    }
}