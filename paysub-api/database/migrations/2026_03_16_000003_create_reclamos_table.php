<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reclamos', function (Blueprint $table) {
            $table->id('id_reclamo');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->cascadeOnDelete();
            $table->foreignId('id_comercio')->nullable()->constrained('comercios', 'id_comercio')->nullOnDelete();
            $table->foreignId('id_suscripcion')->nullable()->constrained('suscripciones', 'id_suscripcion')->nullOnDelete();
            $table->foreignId('id_pago')->nullable()->constrained('pagos', 'id_pago')->nullOnDelete();
            $table->string('codigo', 40)->nullable()->unique();
            $table->string('tipo', 20)->default('reclamo');
            $table->string('categoria', 40)->default('general');
            $table->string('prioridad', 20)->default('media');
            $table->string('estado', 20)->default('abierto');
            $table->string('asunto', 160);
            $table->text('descripcion');
            $table->text('respuesta_admin')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();

            $table->index(['id_usuario', 'estado']);
            $table->index(['tipo', 'estado']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamos');
    }
};
