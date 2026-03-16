<?php

namespace Tests\Feature\Auth;

use App\Mail\RegistroOtpMail;
use App\Models\RegistroOtp;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistroOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_registration_starts_otp_flow_and_sends_mail(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/register-cliente', [
            'nombre' => 'Juan',
            'apellido' => 'Perez',
            'email' => 'juan@correo.com',
            'password' => 'Secret123!',
            'cedula' => 'V-12345678',
            'telefono' => '04141234567',
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('data.email', 'juan@correo.com')
            ->assertJsonPath('data.tipo_usuario', 'cliente');

        $this->assertDatabaseHas('registro_otps', [
            'correo_electronico' => 'juan@correo.com',
            'tipo_usuario' => 'cliente',
            'identificador_unico' => 'V-12345678',
        ]);

        $this->assertDatabaseMissing('usuarios', [
            'correo_electronico' => 'juan@correo.com',
        ]);

        Mail::assertSent(RegistroOtpMail::class);
    }

    public function test_login_is_blocked_while_registration_is_pending(): void
    {
        Mail::fake();

        $this->postJson('/api/register-cliente', [
            'nombre' => 'Ana',
            'apellido' => 'Lopez',
            'email' => 'ana@correo.com',
            'password' => 'Secret123!',
            'cedula' => 'V-87654321',
            'telefono' => '04149876543',
        ])->assertStatus(202);

        $this->postJson('/api/login', [
            'email' => 'ana@correo.com',
            'password' => 'Secret123!',
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED')
            ->assertJsonPath('data.has_pending_verification', true);
    }

    public function test_user_can_verify_otp_and_login_successfully(): void
    {
        Mail::fake();

        $rawOtp = null;

        $this->postJson('/api/register-cliente', [
            'nombre' => 'Maria',
            'apellido' => 'Suarez',
            'email' => 'maria@correo.com',
            'password' => 'Secret123!',
            'cedula' => 'V-11223344',
            'telefono' => '04140001122',
        ])->assertStatus(202);

        Mail::assertSent(RegistroOtpMail::class, function (RegistroOtpMail $mail) use (&$rawOtp) {
            $rawOtp = $mail->otp;

            return true;
        });

        $this->assertNotNull($rawOtp);

        $this->postJson('/api/register/verify-otp', [
            'email' => 'maria@correo.com',
            'otp' => $rawOtp,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.can_login', true);

        $usuario = Usuario::where('correo_electronico', 'maria@correo.com')->first();

        $this->assertNotNull($usuario);
        $this->assertNotNull($usuario->email_verified_at);
        $this->assertDatabaseMissing('registro_otps', [
            'correo_electronico' => 'maria@correo.com',
            'verified_at' => null,
            'consumed_at' => null,
        ]);

        $this->postJson('/api/login', [
            'email' => 'maria@correo.com',
            'password' => 'Secret123!',
        ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'user']);
    }
}
