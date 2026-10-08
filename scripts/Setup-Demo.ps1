$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot
$apiRoot = Join-Path $projectRoot 'paysub-api'
$envPath = Join-Path $apiRoot '.env'
$dbPath = Join-Path $apiRoot 'database/demo.sqlite'
if (Test-Path -LiteralPath $envPath) {
    throw 'Ya existe .env. Consérvalo y configura manualmente una base de demostración; este script no sobrescribe configuraciones.'
}
$key = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$demoPassword = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(16))
@"
APP_NAME=PaySub
APP_ENV=local
APP_KEY=base64:$key
APP_DEBUG=false
APP_URL=http://127.0.0.1:8012
DB_CONNECTION=sqlite
DB_DATABASE=$($dbPath.Replace('\','/'))
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
MAIL_FROM_ADDRESS=demo@paysub.test
MAIL_FROM_NAME=PaySub
PAYMENTS_DEMO_MODE=true
DEMO_PASSWORD=$demoPassword
"@ | Set-Content -LiteralPath $envPath -Encoding utf8
if (!(Test-Path -LiteralPath $dbPath)) { New-Item -ItemType File -Path $dbPath | Out-Null }
Push-Location $apiRoot
try {
    composer install --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) { throw 'composer install falló.' }
    $phpFlags = @()
    php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);'
    if ($LASTEXITCODE -ne 0) { $phpFlags = @('-d', 'extension=pdo_sqlite') }
    php @phpFlags artisan migrate --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Las migraciones fallaron.' }
    php @phpFlags artisan db:seed --class=DemoSeeder --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'DemoSeeder falló.' }
} finally { Pop-Location }
Write-Output 'Demostración local preparada. Lee DEMO_PASSWORD en paysub-api/.env. Las cuentas usan el dominio reservado paysub.test.'
