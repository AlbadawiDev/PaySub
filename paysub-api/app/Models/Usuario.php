<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';

    protected $fillable = [
        'nombre',
        'apellido',
        'correo_electronico',
        'email_verified_at',
        'contrasena',
        'tipo_usuario',
        'cedula',
        'telefono',
        'estado'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'estado' => 'boolean',
    ];

    protected $hidden = [
        'contrasena',
        'remember_token',
    ];

    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    // --- NUEVAS RELACIONES ---

    // Relación con Comercio (Si el usuario es dueño)
    public function comercio()
    {
        return $this->hasOne(Comercio::class, 'id_usuario', 'id_usuario');
    }

    // Relación con Suscripciones (Si el usuario es cliente)
    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class, 'id_usuario', 'id_usuario');
    }

    public function reclamos()
    {
        return $this->hasMany(Reclamo::class, 'id_usuario', 'id_usuario');
    }
}
