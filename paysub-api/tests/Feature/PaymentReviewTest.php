<?php

namespace Tests\Feature;

use App\Models\Comercio;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_payment_is_private_and_requires_receiver_approval(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->user('comercio');
        $buyer = $this->user('cliente');
        $other = $this->user('comercio');
        $commerce = Comercio::create([
            'id_usuario' => $owner->id_usuario, 'nombre_comercio' => 'Demo',
            'rif_identificacion' => 'J-DEMO', 'correo_contacto' => $owner->correo_electronico,
        ]);
        $plan = Plan::create([
            'id_comercio' => $commerce->id_comercio, 'nombre_plan' => 'Monthly',
            'precio' => 20, 'moneda' => 'USD', 'frecuencia' => 'mensual',
            'modalidad_cobro' => 'prepago', 'estado' => true,
        ]);
        Sanctum::actingAs($buyer);
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jG3kAAAAASUVORK5CYII=');
        $this->post('/api/suscripciones', [
            'id_plan' => $plan->id_plan, 'metodo_usado' => 'pago_movil',
            'referencia_operacion' => 'DEMO-123', 'banco_remitente' => 'Demo Bank',
            'telefono_remitente' => '04140000000', 'fecha_pago' => now()->toDateString(),
            'comprobante' => UploadedFile::fake()->createWithContent('receipt.png', $image),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.estado', 'pendiente');
        $payment = Pago::firstOrFail();
        $subscription = Suscripcion::firstOrFail();
        $this->assertSame('en_revision', $payment->estatus_pago);
        Storage::disk('local')->assertExists($payment->comprobante_path);
        Storage::disk('public')->assertMissing($payment->comprobante_path);
        $this->getJson('/api/pagos/'.$payment->id_pago.'/comprobante')->assertOk();
        $this->putJson('/api/pagos/'.$payment->id_pago.'/estado', ['estatus_pago' => 'completado'])->assertForbidden();

        Sanctum::actingAs($other);
        $this->getJson('/api/pagos/'.$payment->id_pago.'/comprobante')->assertForbidden();
        $this->putJson('/api/pagos/'.$payment->id_pago.'/estado', ['estatus_pago' => 'completado'])->assertForbidden();
        Sanctum::actingAs($owner);
        $this->putJson('/api/suscripciones/'.$subscription->id_suscripcion.'/estado', ['estado' => 'activa'])->assertUnprocessable();
        $this->putJson('/api/pagos/'.$payment->id_pago.'/estado', ['estatus_pago' => 'completado'])
            ->assertOk()->assertJsonPath('data.estatus_pago', 'completado');
        $this->assertSame('activa', $subscription->fresh()->estado);
        $this->putJson('/api/pagos/'.$payment->id_pago.'/estado', ['estatus_pago' => 'fallido'])->assertConflict();
    }

    private function user(string $role): Usuario
    {
        return Usuario::create([
            'nombre' => 'Demo', 'apellido' => 'User', 'correo_electronico' => uniqid().'@paysub.test',
            'contrasena' => bcrypt('TestOnly123!'), 'tipo_usuario' => $role,
            'cedula' => uniqid('ID-'), 'estado' => true, 'email_verified_at' => now(),
        ]);
    }
}
