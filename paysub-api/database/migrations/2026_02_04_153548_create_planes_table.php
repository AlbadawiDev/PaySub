<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id('id_plan');
            $table->foreignId('id_comercio')->constrained('comercios', 'id_comercio')->onDelete('cascade');
            $table->string('nombre_plan', 100);
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 12, 2);
            $table->string('moneda', 3)->default('USD');
            $table->string('frecuencia', 50); // semanal, mensual, anual
            
            // Nuevo: Define si se paga antes o después del servicio
            $table->enum('modalidad_cobro', ['prepago', 'postpago'])->default('prepago');
            
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('planes');
    }
};