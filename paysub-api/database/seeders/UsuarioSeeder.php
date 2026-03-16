<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('usuarios')->insert([
            'id_usuario' => 1, 
            'nombre' => 'Daniel',
            'apellido' => 'Tester',
            'correo_electronico' => 'daniel@prueba.com',
            'tipo_usuario' => 'comercio', 
            'cedula' => '30123456',       
            'telefono' => '04141234567',  
            'contrasena' => Hash::make('12345678'),
            'estado' => true, 
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}