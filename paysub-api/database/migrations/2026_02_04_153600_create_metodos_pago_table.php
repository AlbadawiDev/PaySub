<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id('id_metodo_pago');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->onDelete('cascade');

            // tipo_metodo: 'tarjeta', 'paypal'
            $table->string('tipo_metodo', 50)->default('tarjeta');
            $table->string('proveedor', 50)->default('stripe');

            // Datos exclusivos para Tarjetas (Stripe/Pasarelas)
            $table->string('token_pasarela', 255)->unique();
            $table->string('marca_tarjeta', 50)->nullable(); 
            $table->string('ultimos_cuatro', 4)->nullable();
            $table->integer('mes_expiracion')->nullable();
            $table->integer('anio_expiracion')->nullable();

            $table->boolean('es_predeterminado')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('metodos_pago');
    }
};