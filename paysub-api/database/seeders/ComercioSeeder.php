<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComercioSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('comercios')->insert([
            'id_comercio' => 1,
            'id_usuario' => 1, 
            'nombre_comercio' => 'Gimnasio El Fuerte',
            'rif_identificacion' => 'J-123456789',
            'correo_contacto' => 'contacto@gimnasio.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}