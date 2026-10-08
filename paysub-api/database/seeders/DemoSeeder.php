<?php

namespace Database\Seeders;

use App\Models\Comercio;
use App\Models\Plan;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeding is only allowed in local and testing environments.');
        }
        $password = env('DEMO_PASSWORD');
        if (!$password || strlen($password) < 12) {
            throw new \RuntimeException('Set a local DEMO_PASSWORD of at least 12 characters before seeding.');
        }

        foreach (['cliente', 'comercio', 'administrador'] as $index => $role) {
            $user = Usuario::updateOrCreate(['correo_electronico' => $role.'@paysub.test'], [
                'nombre' => ucfirst($role), 'apellido' => 'Demo',
                'contrasena' => Hash::make($password), 'tipo_usuario' => $role,
                'cedula' => 'DEMO-'.($index + 1), 'telefono' => null,
                'email_verified_at' => now(), 'estado' => true,
            ]);
            if ($role === 'comercio') {
                $commerce = Comercio::updateOrCreate(['id_usuario' => $user->id_usuario], [
                    'nombre_comercio' => 'Northstar Studio · Demo', 'rif_identificacion' => 'J-DEMO-001',
                    'correo_contacto' => $user->correo_electronico,
                    'descripcion' => 'Servicios digitales y suscripciones para equipos remotos. Datos ficticios de demostración.',
                    'sitio_web' => 'https://example.test',
                ]);
                \App\Models\DatoPagoComercio::updateOrCreate(['id_comercio' => $commerce->id_comercio], [
                    'banco' => 'Banco de ejemplo (DEMO)', 'telefono_pago' => '00000000000',
                    'rif_cedula' => 'J-DEMO-001', 'titular' => 'Northstar Demo', 'activo' => true,
                ]);
                foreach ([['Starter', 19], ['Studio', 49], ['Enterprise', 99]] as [$name, $price]) {
                    Plan::updateOrCreate(['id_comercio' => $commerce->id_comercio, 'nombre_plan' => $name], [
                        'descripcion' => 'Plan de demostración para '.$name.'. Sin cargos reales.',
                        'precio' => $price, 'moneda' => 'USD', 'frecuencia' => 'mensual',
                        'modalidad_cobro' => 'prepago', 'estado' => true,
                    ]);
                }
            }
        }
    }
}
