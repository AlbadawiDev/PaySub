<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica tu correo</title>
</head>
<body style="margin:0;padding:0;background:#eef4f7;font-family:Arial,sans-serif;color:#11212a;">
    <div style="padding:32px 16px;">
        <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 24px 60px rgba(17,33,42,0.12);">
            <div style="padding:32px;background:linear-gradient(135deg,#0f3d4c 0%,#184f63 55%,#3fb7c6 100%);color:#ffffff;">
                <div style="font-size:13px;letter-spacing:0.18em;text-transform:uppercase;opacity:0.78;">PaySub</div>
                <h1 style="margin:16px 0 10px;font-size:30px;line-height:1.1;">Verifica tu correo</h1>
                <p style="margin:0;font-size:15px;line-height:1.7;max-width:420px;opacity:0.92;">
                    Tu cuenta esta casi lista. Usa el codigo OTP de abajo para completar el registro.
                </p>
            </div>

            <div style="padding:32px;">
                <p style="margin:0 0 12px;font-size:16px;line-height:1.7;">
                    Hola {{ $recipientName }},
                </p>
                <p style="margin:0 0 24px;font-size:15px;line-height:1.7;color:#455a64;">
                    Ingresa este codigo en la pantalla de verificacion de PaySub. El codigo vence en {{ $expiresInMinutes }} minutos.
                </p>

                <div style="margin:0 0 24px;padding:24px;border-radius:20px;background:#f3fbfc;border:1px solid #cbe8ec;text-align:center;">
                    <div style="font-size:13px;letter-spacing:0.18em;text-transform:uppercase;color:#4b6975;margin-bottom:12px;">Codigo OTP</div>
                    <div style="font-size:38px;font-weight:700;letter-spacing:0.35em;color:#0f3d4c;text-indent:0.35em;">
                        {{ $otp }}
                    </div>
                </div>

                <p style="margin:0 0 8px;font-size:14px;line-height:1.7;color:#5c727c;">
                    Si no solicitaste este registro, puedes ignorar este correo.
                </p>
                <p style="margin:0;font-size:14px;line-height:1.7;color:#5c727c;">
                    PaySub nunca te pedira compartir este codigo por telefono o chat.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
