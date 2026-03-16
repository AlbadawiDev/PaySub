<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_otps', function (Blueprint $table) {
            $table->id('id_registro_otp');
            $table->string('correo_electronico', 150)->unique();
            $table->string('tipo_usuario', 20);
            $table->string('identificador_unico', 30);
            $table->json('payload_registro');
            $table->string('otp_hash', 255);
            $table->unsignedSmallInteger('intentos_verificacion')->default(0);
            $table->unsignedSmallInteger('reenvios')->default(0);
            $table->timestamp('ultimo_envio_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('blocked_until')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->unique(['tipo_usuario', 'identificador_unico'], 'registro_otps_tipo_identificador_unique');
            $table->index('expires_at');
            $table->index('blocked_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_otps');
    }
};
