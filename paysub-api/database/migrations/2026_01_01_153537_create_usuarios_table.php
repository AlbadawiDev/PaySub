<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('id_usuario');
            $table->string('nombre');
            // El apellido es opcional para que los comercios no tengan que llenarlo
            $table->string('apellido')->nullable(); 
            $table->string('correo_electronico')->unique();
            $table->string('contrasena');
            $table->string('tipo_usuario', 20); // 'comercio' o 'cliente'
            // Quitamos el nullable: Todos deben identificarse (Cédula o RIF)
            $table->string('cedula')->unique(); 
            $table->string('telefono')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamp('fecha_registro')->useCurrent();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('usuarios');
    }
};