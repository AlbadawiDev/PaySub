<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('datos_pago_comercio', function (Blueprint $table) {
            $table->id('id_dato_pago');
            $table->foreignId('id_comercio')->constrained('comercios', 'id_comercio')->onDelete('cascade');
            
            // Datos para recibir Pago Móvil
            $table->string('banco', 100);
            $table->string('telefono_pago', 20);
            $table->string('rif_cedula', 20);
            $table->string('titular', 100);
            
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('datos_pago_comercio');
    }
};