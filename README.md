# PaySub

Aplicación de portafolio para gestionar planes, suscripciones, reportes de pago y reclamos. Laravel 12 + Sanctum, React 19 + Vite y API REST con roles cliente, comercio y administrador.

## Qué funciona

- Registro con OTP por correo, caducidad, bloqueo por intentos, límite de reenvíos y límite de peticiones.
- Comercios publican planes; clientes reportan pagos móviles con comprobantes privados.
- El comercio receptor revisa cada pago. El prepago mantiene la suscripción pendiente hasta aprobación.
- Cliente consulta suscripciones/pagos, cancela suscripciones y crea reclamos. Administrador responde reclamos y consulta métricas.
- Autorización por propietario en planes, pagos, suscripciones y reclamos. Cuentas deshabilitadas no acceden.
- Los ingresos muestran pagos confirmados en USD, sin mezclar monedas ni contar simulaciones.

Esta versión demuestra gestión y revisión de pagos. No tiene una pasarela real ni ejecuta cargos recurrentes. Las tarjetas sólo pueden simularse con `PAYMENTS_DEMO_MODE=true` en un entorno local; producción las bloquea siempre. No introduzcas datos bancarios o personales reales en la demostración.

## Requisitos y preparación Windows

PHP 8.2 o superior, Composer, Node.js 22 (al menos 22.12) y npm. Activa `pdo_sqlite` para la demostración; PostgreSQL sigue disponible para un despliegue configurado aparte. No se conecta ninguna base original.

Desde esta carpeta:

```powershell
.\scripts\Setup-Demo.ps1
cd paysub-web
npm ci
```

El script crea una `.env` local con clave y contraseña aleatorias y una base nueva `paysub-api/database/demo.sqlite`. No sobrescribe `.env` existente. Si una instalación falla después de crear `.env`, conserva el archivo y completa manualmente `composer install`, `php -d extension=pdo_sqlite artisan migrate` y `php -d extension=pdo_sqlite artisan db:seed --class=DemoSeeder` desde `paysub-api`.

Cuentas ficticias: `cliente@paysub.test`, `comercio@paysub.test`, `administrador@paysub.test`. Lee `DEMO_PASSWORD` únicamente en tu `.env` local. El seeder es idempotente y rechaza producción. Nunca publiques `.env`, SQLite, comprobantes, tokens ni archivos de `storage`.

Inicia dos terminales:

```powershell
# Terminal 1, desde paysub-api
php -d extension=pdo_sqlite -S 127.0.0.1:8012 -t public

# Terminal 2, desde paysub-web
npm run dev -- --host 127.0.0.1 --port 5175 --strictPort
```

Abre `http://127.0.0.1:5175`. Puedes configurar otro API con `VITE_API_BASE_URL` en la `.env` local del frontend. `start-paysub.bat` inicia solamente la API de esta copia.

En Linux/macOS con SQLite cargado puedes usar `php artisan serve --host=127.0.0.1 --port=8012`. El correo local usa `MAIL_MAILER=log`; el OTP queda en el registro privado del servidor. Para SMTP real configura el proveedor y una cola apropiada en el entorno de despliegue.

## Verificación

```powershell
.\scripts\Test.ps1
```

Equivalente portable:

```sh
cd paysub-api
composer install
php vendor/phpunit/phpunit/phpunit
composer audit
cd ../paysub-web
npm ci
npm run build
npm run lint -- --max-warnings=0
npm test
npm audit
```

`phpunit.xml` fuerza SQLite en memoria, correo de prueba y una clave pública exclusiva de tests, incluso si existe `.env` local. En este Windows, `Test.ps1` activa `pdo_sqlite` sólo para su proceso si no está cargado. No uses `artisan migrate:fresh` sobre bases reales.

Resultado local del 7 de octubre de 2026: **25 pruebas PHP, 131 aserciones; 4 pruebas frontend; build y lint sin errores/advertencias; auditorías Composer/npm sin vulnerabilidades conocidas**. GitHub Actions contiene verificaciones equivalentes, pero su ejecución remota debe comprobarse después de publicar.

## Arquitectura y límites

Los controladores aplican autorización explícita por rol y propietario. `CommerceProfileService` comparte validación y escritura entre ambas rutas de perfil. El `TenantMiddleware` heredado es opcional, valida el propietario y restaura el contexto; no está conectado a las rutas de catálogo. Los modelos actuales no extienden todos `BaseTenantModel`, por lo que éste no constituye por sí solo una frontera de seguridad.

Los comprobantes nuevos se guardan en disco privado y se descargan por `GET /api/pagos/{id}/comprobante` tras autorización. `PUT /api/pagos/{id}/estado` acepta `completado` o `fallido` sólo para revisión manual pendiente y actualiza la suscripción dentro de una transacción. Desactivar planes conserva el historial.

Pendiente antes de producción: pruebas de integración PostgreSQL y concurrencia, pasarela verificada con webhooks/idempotencia, renovaciones/notificaciones programadas, paginación de listados grandes y pruebas UI automatizadas adicionales. Los certificados, almacenamiento, SMTP, copias de seguridad y despliegue se configuran en el entorno correspondiente.
