<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id('id_pago');
            $table->foreignId('id_suscripcion')->constrained('suscripciones', 'id_suscripcion')->onDelete('cascade');
            $table->foreignId('id_metodo_pago')->nullable()->constrained('metodos_pago', 'id_metodo_pago')->onDelete('set null');

            $table->decimal('monto', 12, 2);
            $table->string('moneda', 3)->default('USD'); 
            $table->string('estatus_pago', 20)->default('pendiente'); // completado, pendiente, fallido, en_revision
            
            // Tipo de flujo: 'automatico' (Tarjeta) o 'manual' (Pago Móvil)
            $table->string('tipo_flujo', 20)->default('automatico');

            // --- Campos para Reporte de Pago Móvil / Transferencia ---
            $table->string('referencia_operacion', 100)->nullable();
            $table->string('banco_remitente', 100)->nullable();
            $table->string('telefono_remitente', 20)->nullable();
            $table->date('fecha_pago')->nullable();
            $table->string('comprobante_path')->nullable(); // Ruta del capture en el servidor

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pagos');
    }
};