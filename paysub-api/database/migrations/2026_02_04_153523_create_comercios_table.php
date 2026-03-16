<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('comercios', function (Blueprint $table) {
            $table->id('id_comercio');
            
            // Relación con el usuario (dueño)
            $table->foreignId('id_usuario')
                  ->constrained('usuarios', 'id_usuario')
                  ->onDelete('cascade'); 

            $table->string('nombre_comercio', 150);
            
            // Perfil enriquecido
            $table->string('logo', 255)->nullable(); 
            $table->text('descripcion')->nullable();
            $table->string('sitio_web', 255)->nullable();
            $table->json('redes_sociales')->nullable();

            $table->string('rif_identificacion', 20)->unique();
            $table->string('correo_contacto', 150);

            $table->timestamp('fecha_afiliacion')->useCurrent();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('comercios');
    }
};