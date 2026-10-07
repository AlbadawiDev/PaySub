<?php

namespace Tests\Feature;

use App\Models\Comercio;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'cliente', array $overrides = []): Usuario
    {
        return Usuario::create(array_merge([
            'nombre' => 'Demo', 'apellido' => 'User',
            'correo_electronico' => uniqid().'@paysub.test',
            'contrasena' => bcrypt('TestOnly123!'), 'tipo_usuario' => $role,
            'cedula' => uniqid('ID-'), 'estado' => true, 'email_verified_at' => now(),
        ], $overrides));
    }

    private function commerce(Usuario $owner): Comercio
    {
        return Comercio::create([
            'id_usuario' => $owner->id_usuario, 'nombre_comercio' => 'Demo Studio',
            'rif_identificacion' => uniqid('J-'), 'correo_contacto' => $owner->correo_electronico,
        ]);
    }

    public function test_commerce_profile_cannot_transfer_ownership_or_change_identity(): void
    {
        $owner = $this->user('comercio');
        $other = $this->user('comercio');
        $commerce = $this->commerce($owner);
        Sanctum::actingAs($owner);
        $this->putJson('/api/comercio/perfil', [
            'id_usuario' => $other->id_usuario, 'rif_identificacion' => 'J-HIJACK',
            'correo_contacto' => 'hijack@paysub.test', 'nombre_comercio' => 'Hijacked',
        ])->assertStatus(422);
        $this->assertSame($owner->id_usuario, $commerce->fresh()->id_usuario);
        $this->assertSame('Demo Studio', $commerce->fresh()->nombre_comercio);
    }

    public function test_invalid_commerce_profile_returns_validation_error(): void
    {
        $owner = $this->user('comercio');
        $this->commerce($owner);
        Sanctum::actingAs($owner);
        $this->putJson('/api/comercio/perfil', ['sitio_web' => 'javascript:alert(1)'])
            ->assertStatus(422)->assertJsonValidationErrors('sitio_web');
    }

    public function test_both_profile_routes_save_the_actual_logo_and_social_columns(): void
    {
        $owner = $this->user('comercio');
        $commerce = $this->commerce($owner);
        Sanctum::actingAs($owner);
        $this->putJson('/api/user/profile', [
            'logo_url' => 'https://example.test/logo.png',
            'instagram' => 'https://instagram.com/demo', 'telefono' => '04140000000',
        ])->assertOk();
        $this->assertSame('https://example.test/logo.png', $commerce->fresh()->logo);
        $this->assertSame('https://instagram.com/demo', $commerce->fresh()->redes_sociales['instagram']);
    }

    public function test_customer_cannot_read_commerce_billing_settings(): void
    {
        Sanctum::actingAs($this->user());
        $this->getJson('/api/comercio/datos-pago')->assertForbidden();
    }

    public function test_commerce_without_business_gets_not_found_instead_of_crashing(): void
    {
        Sanctum::actingAs($this->user('comercio'));
        $this->getJson('/api/comercio/datos-pago')->assertNotFound();
        $this->postJson('/api/comercio/datos-pago', [
            'banco' => 'Demo', 'telefono_pago' => '04140000000',
            'rif_cedula' => 'J-00000000', 'titular' => 'Demo',
        ])->assertNotFound();
    }

    public function test_payment_gateway_tokens_are_never_serialized(): void
    {
        $client = $this->user();
        MetodoPago::create([
            'id_usuario' => $client->id_usuario, 'tipo_metodo' => 'tarjeta',
            'token_pasarela' => 'test-private-token', 'ultimos_cuatro' => '4242',
        ]);
        Sanctum::actingAs($client);
        $response = $this->getJson('/api/metodos-pago')->assertOk();
        $this->assertArrayNotHasKey('token_pasarela', $response->json('0'));
    }

    public function test_disabled_user_cannot_login_or_use_existing_token(): void
    {
        $client = $this->user('cliente', ['estado' => false]);
        $this->postJson('/api/login', [
            'email' => $client->correo_electronico, 'password' => 'TestOnly123!',
        ])->assertForbidden();
        Sanctum::actingAs($client);
        $this->getJson('/api/user')->assertForbidden();
    }

    public function test_other_commerce_cannot_update_plan_and_customer_cannot_read_other_payments(): void
    {
        $owner = $this->user('comercio');
        $commerce = $this->commerce($owner);
        $plan = Plan::create([
            'id_comercio' => $commerce->id_comercio, 'nombre_plan' => 'Demo monthly',
            'precio' => 10, 'moneda' => 'USD', 'frecuencia' => 'mensual',
            'modalidad_cobro' => 'prepago', 'estado' => true,
        ]);
        $client = $this->user();
        $subscription = Suscripcion::create([
            'id_usuario' => $client->id_usuario, 'id_plan' => $plan->id_plan,
            'fecha_inicio' => now(), 'fecha_fin' => now()->addMonth(), 'estado' => 'activa',
        ]);
        $payment = Pago::create([
            'id_suscripcion' => $subscription->id_suscripcion,
            'monto' => 10, 'moneda' => 'USD', 'estatus_pago' => 'completado', 'tipo_flujo' => 'manual',
        ]);
        $otherOwner = $this->user('comercio');
        $this->commerce($otherOwner);
        Sanctum::actingAs($otherOwner);
        $this->putJson('/api/planes/'.$plan->id_plan, ['precio' => 0])->assertNotFound();
        Sanctum::actingAs($this->user());
        $this->getJson('/api/pagos/'.$payment->id_pago)->assertForbidden();
        $this->getJson('/api/pagos')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/metricas')->assertForbidden();
    }

    public function test_card_simulation_requires_explicit_demo_mode(): void
    {
        $owner = $this->user('comercio');
        $commerce = $this->commerce($owner);
        $plan = Plan::create([
            'id_comercio' => $commerce->id_comercio, 'nombre_plan' => 'Demo monthly',
            'precio' => 10, 'moneda' => 'USD', 'frecuencia' => 'mensual',
            'modalidad_cobro' => 'prepago', 'estado' => true,
        ]);
        $client = $this->user();
        $card = MetodoPago::create([
            'id_usuario' => $client->id_usuario, 'tipo_metodo' => 'tarjeta', 'token_pasarela' => 'test-demo-only',
        ]);
        Sanctum::actingAs($client);
        config(['payments.demo_mode' => false]);
        $this->postJson('/api/suscripciones', [
            'id_plan' => $plan->id_plan, 'metodo_usado' => 'tarjeta', 'id_metodo_pago' => $card->id_metodo_pago,
        ])->assertStatus(503)->assertJsonPath('code', 'PAYMENT_PROVIDER_NOT_CONFIGURED');
        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseCount('suscripciones', 0);
    }

    public function test_logout_revokes_only_the_current_session_token(): void
    {
        $user = $this->user();
        $otherSession = $user->createToken('other-session');
        $currentSession = $user->createToken('current-session');
        $this->withToken($currentSession->plainTextToken)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentSession->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherSession->accessToken->id]);
    }

    public function test_deactivating_a_plan_preserves_cancelled_subscriptions_and_payment_history(): void
    {
        $owner = $this->user('comercio');
        $commerce = $this->commerce($owner);
        $plan = Plan::create([
            'id_comercio' => $commerce->id_comercio, 'nombre_plan' => 'Historical plan',
            'precio' => 10, 'moneda' => 'USD', 'frecuencia' => 'mensual',
            'modalidad_cobro' => 'prepago', 'estado' => true,
        ]);
        $subscription = Suscripcion::create([
            'id_usuario' => $this->user()->id_usuario, 'id_plan' => $plan->id_plan,
            'fecha_inicio' => now()->subMonth(), 'fecha_fin' => now(), 'estado' => 'cancelada',
        ]);
        $payment = Pago::create([
            'id_suscripcion' => $subscription->id_suscripcion, 'monto' => 10,
            'moneda' => 'USD', 'estatus_pago' => 'completado', 'tipo_flujo' => 'manual',
        ]);
        Sanctum::actingAs($owner);
        $this->deleteJson('/api/planes/'.$plan->id_plan)->assertOk();
        $this->assertFalse($plan->fresh()->estado);
        $this->assertDatabaseHas('pagos', ['id_pago' => $payment->id_pago]);
        $this->assertDatabaseHas('suscripciones', ['id_suscripcion' => $subscription->id_suscripcion]);
    }

    public function test_tenant_header_cannot_impersonate_another_commerce_and_context_is_cleared(): void
    {
        $owner = $this->user('comercio');
        $commerce = $this->commerce($owner);
        $request = \Illuminate\Http\Request::create('/api/demo');
        $request->setUserResolver(fn () => $owner);
        $middleware = new \App\Http\Middleware\TenantMiddleware();
        $request->headers->set('X-Comercio-ID', (string) ($commerce->id_comercio + 1));
        $this->assertSame(403, $middleware->handle($request, fn () => response()->json([]))->getStatusCode());
        $request->headers->set('X-Comercio-ID', (string) $commerce->id_comercio);
        $this->assertNull(config('app.comercio_id'));
        $response = $middleware->handle($request, function () use ($commerce) {
            $this->assertSame($commerce->id_comercio, config('app.comercio_id'));
            return response()->json([]);
        });
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull(config('app.comercio_id'));
    }
}
