<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Llamamos a los seeders que ya arreglaste
        $this->call([
            UsuarioSeeder::class,
            ComercioSeeder::class,
            PlanSeeder::class,
        ]);

        // 2. Creamos un "Cliente" falso para que te compre un plan
        DB::table('usuarios')->insert([
            'id_usuario' => 2,
            'nombre' => 'Juan',
            'apellido' => 'Comprador',
            'correo_electronico' => 'juan@cliente.com',
            'tipo_usuario' => 'cliente',
            'cedula' => '25123456',
            'telefono' => '04149876543',
            'contrasena' => Hash::make('12345678'),
            'estado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Le asignamos una Suscripción al Plan Básico (id_plan = 1)
        DB::table('suscripciones')->insert([
            'id_suscripcion' => 1,
            'id_usuario' => 2, 
            'id_plan' => 1,    
            'fecha_inicio' => now(),
            'fecha_fin' => now()->addMonth(), 
            'estado' => 'Activa', 
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}