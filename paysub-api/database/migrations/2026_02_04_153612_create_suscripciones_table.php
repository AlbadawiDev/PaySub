<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id('id_suscripcion');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->onDelete('cascade');
            $table->foreignId('id_plan')->constrained('planes', 'id_plan')->onDelete('cascade');
            $table->timestamp('fecha_inicio')->useCurrent();
            $table->timestamp('fecha_fin')->nullable();
            $table->string('estado', 20)->default('activa'); 
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('suscripciones');
    }
};