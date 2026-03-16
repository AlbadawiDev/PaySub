<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Insertamos o actualizamos los planes con la nueva estructura de la BD
        DB::table('planes')->upsert([
            [
                'id_plan' => 1,
                'id_comercio' => 1,                  
                'nombre_plan' => 'Plan Básico',      
                'descripcion' => 'Acceso limitado, ideal para probar.',
                'precio' => 10.00,
                'moneda' => 'USD',
                'frecuencia' => 'mensual',           
                'modalidad_cobro' => 'prepago',      
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_plan' => 2,
                'id_comercio' => 1,                  
                'nombre_plan' => 'Plan Premium',     
                'descripcion' => 'Acceso completo sin publicidad.',
                'precio' => 25.00,
                'moneda' => 'USD',
                'frecuencia' => 'mensual',           
                'modalidad_cobro' => 'prepago',      
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_plan' => 3,
                'id_comercio' => 1,                  
                'nombre_plan' => 'Plan Pro (Anual)', 
                'descripcion' => 'Ahorra con el pago anual. Todo incluido.',
                'precio' => 250.00,
                'moneda' => 'USD',
                'frecuencia' => 'anual',             
                'modalidad_cobro' => 'prepago',      
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ], ['id_plan'], ['id_comercio', 'nombre_plan', 'descripcion', 'precio', 'frecuencia', 'modalidad_cobro', 'estado']);
    }
}