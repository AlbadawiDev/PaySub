$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot
Push-Location (Join-Path $projectRoot 'paysub-api')
try {
    $phpFlags = @()
    php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);'
    if ($LASTEXITCODE -ne 0) { $phpFlags = @('-d', 'extension=pdo_sqlite') }
    php @phpFlags vendor/phpunit/phpunit/phpunit --colors=never
    if ($LASTEXITCODE -ne 0) { throw 'Pruebas PHP fallidas.' }
} finally { Pop-Location }
Push-Location (Join-Path $projectRoot 'paysub-web')
try {
    npm run build
    if ($LASTEXITCODE -ne 0) { throw 'Build fallido.' }
    npm run lint -- --max-warnings=0
    if ($LASTEXITCODE -ne 0) { throw 'Lint fallido.' }
    npm test
    if ($LASTEXITCODE -ne 0) { throw 'Pruebas frontend fallidas.' }
} finally { Pop-Location }
