<?php

namespace Tests\Feature;

use App\Models\Comercio;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\Reclamo;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReclamoModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_create_list_and_view_own_claims(): void
    {
        $client = $this->createUser([
            'correo_electronico' => 'cliente@paysub.test',
            'tipo_usuario' => 'cliente',
            'cedula' => 'V-10000001',
        ]);

        $commerceOwner = $this->createUser([
            'correo_electronico' => 'comercio@paysub.test',
            'tipo_usuario' => 'comercio',
            'cedula' => 'J-10000001',
        ]);

        $commerce = Comercio::create([
            'id_usuario' => $commerceOwner->id_usuario,
            'nombre_comercio' => 'Studio Prime',
            'rif_identificacion' => 'J-10000001',
            'correo_contacto' => 'comercio@paysub.test',
            'sitio_web' => 'https://studio-prime.test',
        ]);

        $plan = Plan::create([
            'id_comercio' => $commerce->id_comercio,
            'nombre_plan' => 'Pro anual',
            'descripcion' => 'Plan anual del comercio.',
            'precio' => 29.99,
            'frecuencia' => 'anual',
            'moneda' => 'USD',
            'modalidad_cobro' => 'prepago',
            'estado' => true,
        ]);

        $subscription = Suscripcion::create([
            'id_usuario' => $client->id_usuario,
            'id_plan' => $plan->id_plan,
            'fecha_inicio' => now(),
            'fecha_fin' => now()->addYear(),
            'estado' => 'activa',
        ]);

        $payment = Pago::create([
            'id_suscripcion' => $subscription->id_suscripcion,
            'monto' => 29.99,
            'moneda' => 'USD',
            'estatus_pago' => 'completado',
            'tipo_flujo' => 'automatico',
            'referencia_operacion' => 'PAY-1000',
        ]);

        Sanctum::actingAs($client);

        $createResponse = $this->postJson('/api/reclamos', [
            'tipo' => 'reclamo',
            'categoria' => 'pagos',
            'prioridad' => 'alta',
            'asunto' => 'Cobro duplicado en marzo',
            'descripcion' => 'Se detecto un segundo cobro en la misma fecha para la suscripcion asociada.',
            'id_suscripcion' => $subscription->id_suscripcion,
            'id_pago' => $payment->id_pago,
        ]);

        $createResponse
            ->assertStatus(201)
            ->assertJsonPath('data.tipo', 'reclamo')
            ->assertJsonPath('data.estado', 'abierto')
            ->assertJsonPath('data.comercio.nombre_comercio', 'Studio Prime');

        $claimId = $createResponse->json('data.id_reclamo');

        $this->assertDatabaseHas('reclamos', [
            'id_reclamo' => $claimId,
            'id_usuario' => $client->id_usuario,
            'id_comercio' => $commerce->id_comercio,
            'estado' => 'abierto',
        ]);

        $this->getJson('/api/reclamos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_reclamo', $claimId);

        $this->getJson("/api/reclamos/{$claimId}")
            ->assertOk()
            ->assertJsonPath('data.id_reclamo', $claimId)
            ->assertJsonPath('data.pago.referencia_operacion', 'PAY-1000');
    }

    public function test_client_cannot_view_another_clients_claim(): void
    {
        $owner = $this->createUser([
            'correo_electronico' => 'owner@paysub.test',
            'tipo_usuario' => 'cliente',
            'cedula' => 'V-20000001',
        ]);

        $otherClient = $this->createUser([
            'correo_electronico' => 'other@paysub.test',
            'tipo_usuario' => 'cliente',
            'cedula' => 'V-20000002',
        ]);

        $claim = Reclamo::create([
            'id_usuario' => $owner->id_usuario,
            'tipo' => 'reclamo',
            'categoria' => 'general',
            'prioridad' => 'media',
            'estado' => 'abierto',
            'asunto' => 'Caso privado',
            'descripcion' => 'Este reclamo solo debe ser visible para su propietario.',
            'codigo' => 'RCL-TEST-0001',
        ]);

        Sanctum::actingAs($otherClient);

        $this->getJson("/api/reclamos/{$claim->id_reclamo}")
            ->assertStatus(403)
            ->assertJsonPath('error', 'No autorizado para ver este reclamo.');
    }

    public function test_admin_can_manage_claims_and_view_global_metrics(): void
    {
        $admin = $this->createUser([
            'correo_electronico' => 'admin@paysub.test',
            'tipo_usuario' => 'administrador',
            'cedula' => 'V-30000001',
        ]);

        $client = $this->createUser([
            'correo_electronico' => 'buyer@paysub.test',
            'tipo_usuario' => 'cliente',
            'cedula' => 'V-30000002',
        ]);

        $commerceOwner = $this->createUser([
            'correo_electronico' => 'biz@paysub.test',
            'tipo_usuario' => 'comercio',
            'cedula' => 'J-30000001',
        ]);

        $commerce = Comercio::create([
            'id_usuario' => $commerceOwner->id_usuario,
            'nombre_comercio' => 'Cloud Norte',
            'rif_identificacion' => 'J-30000001',
            'correo_contacto' => 'biz@paysub.test',
            'sitio_web' => 'https://cloud-norte.test',
        ]);

        $plan = Plan::create([
            'id_comercio' => $commerce->id_comercio,
            'nombre_plan' => 'Suite premium',
            'descripcion' => 'Plan con cobro recurrente.',
            'precio' => 49.99,
            'frecuencia' => 'mensual',
            'moneda' => 'USD',
            'modalidad_cobro' => 'prepago',
            'estado' => true,
        ]);

        $subscription = Suscripcion::create([
            'id_usuario' => $client->id_usuario,
            'id_plan' => $plan->id_plan,
            'fecha_inicio' => now(),
            'fecha_fin' => now()->addMonth(),
            'estado' => 'activa',
        ]);

        Pago::create([
            'id_suscripcion' => $subscription->id_suscripcion,
            'monto' => 49.99,
            'moneda' => 'USD',
            'estatus_pago' => 'completado',
            'tipo_flujo' => 'automatico',
            'referencia_operacion' => 'PAY-3000',
        ]);

        $claim = Reclamo::create([
            'id_usuario' => $client->id_usuario,
            'id_comercio' => $commerce->id_comercio,
            'id_suscripcion' => $subscription->id_suscripcion,
            'tipo' => 'solicitud',
            'categoria' => 'suscripciones',
            'prioridad' => 'media',
            'estado' => 'abierto',
            'asunto' => 'Cambio de fecha de cobro',
            'descripcion' => 'Se solicita mover la fecha de facturacion para el inicio del siguiente mes.',
            'codigo' => 'RCL-TEST-0002',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/reclamos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.codigo', 'RCL-TEST-0002');

        $this->putJson("/api/reclamos/{$claim->id_reclamo}/estado", [
            'estado' => 'resuelto',
            'respuesta_admin' => 'Se actualizo el caso y el siguiente ciclo ya usara la fecha solicitada.',
        ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'resuelto')
            ->assertJsonPath('data.respuesta_admin', 'Se actualizo el caso y el siguiente ciclo ya usara la fecha solicitada.');

        $this->assertDatabaseHas('reclamos', [
            'id_reclamo' => $claim->id_reclamo,
            'estado' => 'resuelto',
        ]);

        $this->getJson('/api/admin/metricas')
            ->assertOk()
            ->assertJsonPath('data.overview.usuarios_total', 3)
            ->assertJsonPath('data.overview.clientes_total', 1)
            ->assertJsonPath('data.overview.comercios_total', 1)
            ->assertJsonPath('data.overview.suscripciones_activas', 1)
            ->assertJsonPath('data.overview.ingresos_totales', 49.99)
            ->assertJsonPath('data.overview.reclamos_total', 1)
            ->assertJsonPath('data.overview.reclamos_resueltos', 1);
    }

    private function createUser(array $overrides): Usuario
    {
        return Usuario::create(array_merge([
            'nombre' => 'Test',
            'apellido' => 'User',
            'correo_electronico' => 'user-' . uniqid() . '@paysub.test',
            'contrasena' => bcrypt('secret123'),
            'tipo_usuario' => 'cliente',
            'cedula' => 'ID-' . uniqid(),
            'telefono' => '04140000000',
            'estado' => true,
            'email_verified_at' => now(),
        ], $overrides));
    }
}
