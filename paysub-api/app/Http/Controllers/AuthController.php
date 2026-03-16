<?php

namespace App\Http\Controllers;

use App\Mail\RegistroOtpMail;
use App\Models\Comercio;
use App\Models\RegistroOtp;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private const OTP_EXPIRATION_MINUTES = 10;
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_MAX_RESENDS = 3;
    private const OTP_RESEND_COOLDOWN_SECONDS = 60;

    /**
     * REGISTRO PARA COMERCIOS
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_comercio' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'rif' => 'required|string|max:30',
            'telefono' => 'nullable|string|max:20',
            'web' => 'nullable|url|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payload = $this->buildComercioPayload($request);

        return $this->startOtpRegistration($payload);
    }

    /**
     * REGISTRO PARA CLIENTES
     */
    public function registerCliente(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'cedula' => 'required|string|max:30',
            'telefono' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payload = $this->buildClientePayload($request);

        return $this->startOtpRegistration($payload);
    }

    public function verifyRegistrationOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $email = $this->normalizeEmail($request->email);
        $registroOtp = RegistroOtp::where('correo_electronico', $email)->first();

        if (!$registroOtp) {
            return response()->json([
                'mensaje' => 'No existe un registro pendiente para este correo.',
                'code' => 'OTP_NOT_FOUND',
            ], 404);
        }

        if ($registroOtp->verified_at || $registroOtp->consumed_at) {
            return response()->json([
                'mensaje' => 'Este registro ya fue verificado.',
                'code' => 'OTP_ALREADY_CONSUMED',
            ], 409);
        }

        if ($registroOtp->isBlocked()) {
            return response()->json([
                'mensaje' => 'Has agotado los intentos. Debes solicitar un nuevo codigo.',
                'code' => 'OTP_BLOCKED',
            ], 423);
        }

        if ($registroOtp->isExpired()) {
            return response()->json([
                'mensaje' => 'El codigo OTP expiro. Solicita uno nuevo.',
                'code' => 'OTP_EXPIRED',
            ], 410);
        }

        if (!Hash::check($request->otp, $registroOtp->otp_hash)) {
            $registroOtp->intentos_verificacion++;

            if ($registroOtp->intentos_verificacion >= self::OTP_MAX_ATTEMPTS) {
                $registroOtp->blocked_until = now()->addMinutes(self::OTP_EXPIRATION_MINUTES);
                $registroOtp->save();

                return response()->json([
                    'mensaje' => 'Has agotado los intentos. Debes solicitar un nuevo codigo.',
                    'code' => 'OTP_BLOCKED',
                ], 423);
            }

            $registroOtp->save();

            return response()->json([
                'mensaje' => 'El codigo OTP es invalido.',
                'code' => 'OTP_INVALID',
                'data' => [
                    'attempts_left' => self::OTP_MAX_ATTEMPTS - $registroOtp->intentos_verificacion,
                ],
            ], 422);
        }

        try {
            $usuario = DB::transaction(function () use ($registroOtp) {
                $payload = $registroOtp->payload_registro;
                $usuarioData = $payload['usuario'];
                $comercioData = $payload['comercio'] ?? null;

                if (
                    Usuario::where('correo_electronico', $registroOtp->correo_electronico)->exists() ||
                    Usuario::where('cedula', $registroOtp->identificador_unico)->exists()
                ) {
                    throw new \DomainException('REGISTRATION_CONFLICT');
                }

                if (
                    $comercioData !== null &&
                    Comercio::where('rif_identificacion', $comercioData['rif_identificacion'])->exists()
                ) {
                    throw new \DomainException('REGISTRATION_CONFLICT');
                }

                $usuario = Usuario::create([
                    'nombre' => $usuarioData['nombre'],
                    'apellido' => $usuarioData['apellido'],
                    'correo_electronico' => $usuarioData['correo_electronico'],
                    'contrasena' => $usuarioData['contrasena'],
                    'tipo_usuario' => $usuarioData['tipo_usuario'],
                    'cedula' => $usuarioData['cedula'],
                    'telefono' => $usuarioData['telefono'],
                    'estado' => $usuarioData['estado'],
                    'email_verified_at' => now(),
                ]);

                if ($comercioData !== null) {
                    Comercio::create([
                        'id_usuario' => $usuario->id_usuario,
                        'nombre_comercio' => $comercioData['nombre_comercio'],
                        'rif_identificacion' => $comercioData['rif_identificacion'],
                        'correo_contacto' => $comercioData['correo_contacto'],
                        'sitio_web' => $comercioData['sitio_web'],
                    ]);
                }

                $registroOtp->update([
                    'verified_at' => now(),
                    'consumed_at' => now(),
                    'blocked_until' => null,
                ]);

                return $usuario;
            });
        } catch (\DomainException $exception) {
            return response()->json([
                'mensaje' => 'Ya existe una cuenta con los datos suministrados.',
                'code' => 'REGISTRATION_CONFLICT',
            ], 409);
        } catch (\Throwable $exception) {
            return response()->json([
                'mensaje' => 'No fue posible completar el registro.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'mensaje' => 'Correo verificado y cuenta creada exitosamente.',
            'data' => [
                'email' => $usuario->correo_electronico,
                'tipo_usuario' => $usuario->tipo_usuario,
                'can_login' => true,
                'redirect_to' => '/login',
            ],
        ], 201);
    }

    public function resendRegistrationOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $email = $this->normalizeEmail($request->email);
        $registroOtp = RegistroOtp::where('correo_electronico', $email)->first();

        if (!$registroOtp) {
            return response()->json([
                'mensaje' => 'No existe un registro pendiente para este correo.',
                'code' => 'OTP_NOT_FOUND',
            ], 404);
        }

        if ($registroOtp->verified_at || $registroOtp->consumed_at) {
            return response()->json([
                'mensaje' => 'Este registro ya fue verificado.',
                'code' => 'OTP_ALREADY_CONSUMED',
            ], 409);
        }

        try {
            return $this->refreshOtp($registroOtp, true);
        } catch (\Throwable $exception) {
            return response()->json([
                'mensaje' => 'No fue posible reenviar el codigo OTP.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $this->normalizeEmail($request->email);
        $registroPendiente = RegistroOtp::where('correo_electronico', $email)
            ->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->first();

        if ($registroPendiente) {
            return response()->json([
                'mensaje' => 'Debes verificar tu correo antes de iniciar sesion.',
                'code' => 'EMAIL_NOT_VERIFIED',
                'data' => [
                    'email' => $email,
                    'has_pending_verification' => true,
                ],
            ], 403);
        }

        $usuario = Usuario::where('correo_electronico', $email)->first();

        if (!$usuario || !Hash::check($request->password, $usuario->contrasena)) {
            return response()->json(['mensaje' => 'Credenciales incorrectas'], 401);
        }

        if ($usuario->email_verified_at === null) {
            return response()->json([
                'mensaje' => 'Debes verificar tu correo antes de iniciar sesion.',
                'code' => 'EMAIL_NOT_VERIFIED',
                'data' => [
                    'email' => $email,
                    'has_pending_verification' => false,
                ],
            ], 403);
        }

        $token = $usuario->createToken('auth_token')->plainTextToken;

        $comercio = null;
        if ($usuario->tipo_usuario === 'comercio') {
            $comercio = Comercio::where('id_usuario', $usuario->id_usuario)->first();
        }

        return response()->json([
            'mensaje' => 'Login exitoso',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $usuario,
            'comercio' => $comercio,
        ], 200);
    }

    /**
     * ACTUALIZAR PERFIL
     * Cliente: Solo teléfono.
     * Comercio: Identidad de negocio (NO nombre_comercio).
     */
    public function updateProfile(Request $request)
    {
        /** @var \App\Models\Usuario $usuario */
        $usuario = Auth::user();

        if ($usuario->tipo_usuario === 'comercio' && $request->has('nombre_comercio')) {
            return response()->json([
                'errors' => [
                    'nombre_comercio' => ['El nombre del comercio no puede ser editado por seguridad.'],
                ],
            ], 422);
        }

        if ($usuario->tipo_usuario === 'comercio') {
            $rules = [
                'telefono' => 'nullable|string|max:20',
                'sitio_web' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string|max:500',
                'logo_url' => 'nullable|string',
                'facebook' => 'nullable|string',
                'instagram' => 'nullable|string',
                'twitter' => 'nullable|string',
            ];
        } else {
            $rules = [
                'telefono' => 'nullable|string|max:20',
            ];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            return DB::transaction(function () use ($request, $usuario) {
                if ($usuario->tipo_usuario === 'comercio') {
                    $comercio = Comercio::where('id_usuario', $usuario->id_usuario)->first();
                    if ($comercio) {
                        $comercio->update($request->only([
                            'sitio_web',
                            'descripcion',
                            'logo_url',
                            'facebook',
                            'instagram',
                            'twitter',
                        ]));

                        $usuario->update([
                            'telefono' => $request->telefono ?? $usuario->telefono,
                        ]);
                    }
                } else {
                    $usuario->update($request->only(['telefono']));
                }

                return response()->json([
                    'mensaje' => 'Perfil actualizado exitosamente',
                    'user' => $usuario->load($usuario->tipo_usuario === 'comercio' ? 'comercio' : 'suscripciones'),
                ], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['mensaje' => 'Sesión cerrada exitosamente'], 200);
    }

    public function userProfile(Request $request)
    {
        /** @var \App\Models\Usuario $usuario */
        $usuario = $request->user();

        if ($usuario->tipo_usuario === 'cliente') {
            $usuario->load(['suscripciones' => function ($query) {
                $query->where('estado', 'activa')->with('plan');
            }]);
        } elseif ($usuario->tipo_usuario === 'comercio') {
            $usuario->load('comercio');
        }

        return response()->json([
            'mensaje' => 'Perfil recuperado exitosamente',
            'data' => $usuario,
        ], 200);
    }

    private function startOtpRegistration(array $payload)
    {
        $conflictResponse = $this->validateRegistrationConflicts($payload);
        if ($conflictResponse !== null) {
            return $conflictResponse;
        }

        $registroOtp = RegistroOtp::where('correo_electronico', $payload['correo_electronico'])->first();

        if ($registroOtp && ($registroOtp->verified_at || $registroOtp->consumed_at)) {
            return response()->json([
                'mensaje' => 'Este registro ya fue verificado.',
                'code' => 'OTP_ALREADY_CONSUMED',
            ], 409);
        }

        $countAsResend = $registroOtp !== null && $registroOtp->ultimo_envio_at !== null;

        if (!$registroOtp) {
            $registroOtp = new RegistroOtp();
        }

        $registroOtp->fill([
            'correo_electronico' => $payload['correo_electronico'],
            'tipo_usuario' => $payload['tipo_usuario'],
            'identificador_unico' => $payload['identificador_unico'],
            'payload_registro' => $payload['payload_registro'],
        ]);

        try {
            return $this->refreshOtp($registroOtp, $countAsResend);
        } catch (\Throwable $exception) {
            return response()->json([
                'mensaje' => 'No fue posible enviar el codigo OTP.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    private function refreshOtp(RegistroOtp $registroOtp, bool $countAsResend)
    {
        if ($countAsResend && !$registroOtp->canResend(self::OTP_MAX_RESENDS, self::OTP_RESEND_COOLDOWN_SECONDS)) {
            return response()->json([
                'mensaje' => $registroOtp->reenvios >= self::OTP_MAX_RESENDS
                    ? 'Has alcanzado el limite de reenvios.'
                    : 'Debes esperar antes de solicitar un nuevo codigo.',
                'code' => 'OTP_RESEND_LIMIT',
                'data' => [
                    'resend_in' => $this->secondsUntilResend($registroOtp),
                    'resends_left' => max(0, self::OTP_MAX_RESENDS - $registroOtp->reenvios),
                ],
            ], 429);
        }

        $otp = (string) random_int(100000, 999999);

        $registroOtp->otp_hash = Hash::make($otp);
        $registroOtp->intentos_verificacion = 0;
        $registroOtp->blocked_until = null;
        $registroOtp->expires_at = now()->addMinutes(self::OTP_EXPIRATION_MINUTES);
        $registroOtp->ultimo_envio_at = now();
        $registroOtp->verified_at = null;
        $registroOtp->consumed_at = null;

        if (!$registroOtp->exists) {
            $registroOtp->reenvios = 0;
        } elseif ($countAsResend) {
            $registroOtp->reenvios++;
        }

        $registroOtp->save();

        $recipientName = $this->resolveRecipientName($registroOtp);
        Mail::to($registroOtp->correo_electronico)->send(
            new RegistroOtpMail($otp, $recipientName, self::OTP_EXPIRATION_MINUTES)
        );

        return response()->json([
            'mensaje' => $countAsResend
                ? 'Nuevo codigo OTP enviado al correo.'
                : 'Codigo OTP enviado al correo.',
            'data' => [
                'email' => $registroOtp->correo_electronico,
                'email_masked' => $this->maskEmail($registroOtp->correo_electronico),
                'tipo_usuario' => $registroOtp->tipo_usuario,
                'expires_in' => self::OTP_EXPIRATION_MINUTES * 60,
                'resend_in' => self::OTP_RESEND_COOLDOWN_SECONDS,
                'resends_left' => max(0, self::OTP_MAX_RESENDS - $registroOtp->reenvios),
            ],
        ], $countAsResend ? 200 : 202);
    }

    private function validateRegistrationConflicts(array $payload)
    {
        $email = $payload['correo_electronico'];
        $identifier = $payload['identificador_unico'];
        $tipoUsuario = $payload['tipo_usuario'];

        $emailTaken = Usuario::where('correo_electronico', $email)->exists();
        if ($emailTaken) {
            return response()->json([
                'errors' => [
                    'email' => ['Ya existe una cuenta registrada con este correo.'],
                ],
            ], 422);
        }

        $identifierTaken = Usuario::where('cedula', $identifier)->exists();
        if ($identifierTaken) {
            $field = $tipoUsuario === 'cliente' ? 'cedula' : 'rif';

            return response()->json([
                'errors' => [
                    $field => ['Ya existe una cuenta registrada con este identificador.'],
                ],
            ], 422);
        }

        if ($tipoUsuario === 'comercio') {
            $rif = $payload['payload_registro']['comercio']['rif_identificacion'];
            if (Comercio::where('rif_identificacion', $rif)->exists()) {
                return response()->json([
                    'errors' => [
                        'rif' => ['Ya existe un comercio registrado con este RIF.'],
                    ],
                ], 422);
            }
        }

        $duplicatePending = RegistroOtp::query()
            ->where('identificador_unico', $identifier)
            ->where('tipo_usuario', $tipoUsuario)
            ->where('correo_electronico', '!=', $email)
            ->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->exists();

        if ($duplicatePending) {
            $field = $tipoUsuario === 'cliente' ? 'cedula' : 'rif';

            return response()->json([
                'errors' => [
                    $field => ['Ya existe un registro pendiente con este identificador.'],
                ],
            ], 422);
        }

        return null;
    }

    private function buildComercioPayload(Request $request): array
    {
        $email = $this->normalizeEmail($request->email);
        $rif = $this->normalizeIdentifier($request->rif);

        return [
            'correo_electronico' => $email,
            'tipo_usuario' => 'comercio',
            'identificador_unico' => $rif,
            'payload_registro' => [
                'usuario' => [
                    'nombre' => trim($request->nombre_comercio),
                    'apellido' => 'Comercio',
                    'correo_electronico' => $email,
                    'contrasena' => Hash::make($request->password),
                    'tipo_usuario' => 'comercio',
                    'cedula' => $rif,
                    'telefono' => $request->telefono ? trim($request->telefono) : null,
                    'estado' => true,
                ],
                'comercio' => [
                    'nombre_comercio' => trim($request->nombre_comercio),
                    'rif_identificacion' => $rif,
                    'correo_contacto' => $email,
                    'sitio_web' => $request->web ? trim($request->web) : '',
                ],
            ],
        ];
    }

    private function buildClientePayload(Request $request): array
    {
        $email = $this->normalizeEmail($request->email);
        $cedula = $this->normalizeIdentifier($request->cedula);

        return [
            'correo_electronico' => $email,
            'tipo_usuario' => 'cliente',
            'identificador_unico' => $cedula,
            'payload_registro' => [
                'usuario' => [
                    'nombre' => trim($request->nombre),
                    'apellido' => trim($request->apellido),
                    'correo_electronico' => $email,
                    'contrasena' => Hash::make($request->password),
                    'tipo_usuario' => 'cliente',
                    'cedula' => $cedula,
                    'telefono' => $request->telefono ? trim($request->telefono) : null,
                    'estado' => true,
                ],
            ],
        ];
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function normalizeIdentifier(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($value)));
    }

    private function maskEmail(string $email): string
    {
        [$localPart, $domain] = explode('@', $email);
        $visible = substr($localPart, 0, min(2, strlen($localPart)));

        return $visible . '***@' . $domain;
    }

    private function resolveRecipientName(RegistroOtp $registroOtp): string
    {
        $payload = $registroOtp->payload_registro;
        if ($registroOtp->tipo_usuario === 'comercio') {
            return $payload['comercio']['nombre_comercio'] ?? 'PaySub';
        }

        return trim(($payload['usuario']['nombre'] ?? '') . ' ' . ($payload['usuario']['apellido'] ?? ''));
    }

    private function secondsUntilResend(RegistroOtp $registroOtp): int
    {
        if ($registroOtp->ultimo_envio_at === null) {
            return 0;
        }

        $availableAt = $registroOtp->ultimo_envio_at
            ->copy()
            ->addSeconds(self::OTP_RESEND_COOLDOWN_SECONDS);

        if (now()->greaterThanOrEqualTo($availableAt)) {
            return 0;
        }

        return now()->diffInSeconds($availableAt);
    }
}
