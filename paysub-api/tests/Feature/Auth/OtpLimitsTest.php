<?php

namespace Tests\Feature\Auth;

use App\Mail\RegistroOtpMail;
use App\Models\RegistroOtp;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function register(): string
    {
        Mail::fake();
        $this->postJson('/api/register-cliente', [
            'nombre' => 'Demo', 'apellido' => 'Test', 'email' => 'limits@paysub.test',
            'password' => 'TestOnly123!', 'cedula' => 'V-DEMO-123',
        ])->assertStatus(202);
        $otp = '';
        Mail::assertSent(RegistroOtpMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });
        return $otp;
    }

    public function test_otp_attempt_limit_expiration_and_replay_are_enforced(): void
    {
        $otp = $this->register();
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/register/verify-otp', ['email' => 'limits@paysub.test', 'otp' => '000000'])
                ->assertStatus($attempt === 5 ? 423 : 422);
        }
        $this->postJson('/api/register/verify-otp', ['email' => 'limits@paysub.test', 'otp' => $otp])->assertStatus(423);
        $this->assertDatabaseCount('usuarios', 0);
        $this->travel(11)->minutes();
        $this->postJson('/api/register/verify-otp', ['email' => 'limits@paysub.test', 'otp' => $otp])->assertStatus(410);
    }

    public function test_resend_cooldown_does_not_mutate_the_original_send_time(): void
    {
        $this->register();
        $record = RegistroOtp::firstOrFail();
        $original = $record->ultimo_envio_at->copy();
        $this->assertFalse($record->canResend(3, 60));
        $this->assertTrue($record->ultimo_envio_at->equalTo($original));
        $this->postJson('/api/register/resend-otp', ['email' => 'limits@paysub.test'])
            ->assertStatus(429)->assertJsonPath('data.resends_left', 3);
        $this->travel(61)->seconds();
        $this->postJson('/api/register/resend-otp', ['email' => 'limits@paysub.test'])
            ->assertOk()->assertJsonPath('data.resends_left', 2);
    }

    public function test_consumed_otp_cannot_create_a_second_account(): void
    {
        $otp = $this->register();
        $this->postJson('/api/register/verify-otp', ['email' => 'limits@paysub.test', 'otp' => $otp])->assertCreated();
        $this->postJson('/api/register/verify-otp', ['email' => 'limits@paysub.test', 'otp' => $otp])->assertConflict();
        $this->assertSame(1, Usuario::count());
    }

    public function test_login_endpoint_limits_repeated_password_guesses(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'unknown@paysub.test', 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/api/login', ['email' => 'unknown@paysub.test', 'password' => 'wrong'])->assertStatus(429);
    }
}
