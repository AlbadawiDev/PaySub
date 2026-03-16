<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroOtp extends Model
{
    protected $table = 'registro_otps';
    protected $primaryKey = 'id_registro_otp';

    protected $fillable = [
        'correo_electronico',
        'tipo_usuario',
        'identificador_unico',
        'payload_registro',
        'otp_hash',
        'intentos_verificacion',
        'reenvios',
        'ultimo_envio_at',
        'expires_at',
        'blocked_until',
        'verified_at',
        'consumed_at',
    ];

    protected $casts = [
        'payload_registro' => 'array',
        'ultimo_envio_at' => 'datetime',
        'expires_at' => 'datetime',
        'blocked_until' => 'datetime',
        'verified_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isBlocked(): bool
    {
        return $this->blocked_until !== null && $this->blocked_until->isFuture();
    }

    public function canResend(int $maxResends, int $cooldownSeconds): bool
    {
        if ($this->reenvios >= $maxResends) {
            return false;
        }

        if ($this->ultimo_envio_at === null) {
            return true;
        }

        return $this->ultimo_envio_at->addSeconds($cooldownSeconds)->isPast();
    }
}
