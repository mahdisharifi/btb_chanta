# Laravel deployment preparation; run locally before uploading.
[CmdletBinding()]
param([switch]$NoPause)

function Invoke-DeployCommand {
    param(
        [Parameter(Mandatory = $true)][string]$Command,
        [Parameter(Mandatory = $true)][string[]]$Arguments
    )

    & $Command @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$Command $($Arguments -join ' ') failed (exit code $LASTEXITCODE)."
    }
}

$previousErrorActionPreference = $ErrorActionPreference
$ErrorActionPreference = 'Stop'
$deployExitCode = 0
$locationPushed = $false

try {
    Write-Host 'Preparing Project for Deployment' -ForegroundColor Cyan
    Push-Location -LiteralPath $PSScriptRoot
    $locationPushed = $true

    Write-Host '[1/6] Checking prerequisites...' -ForegroundColor Yellow
    foreach ($commandName in @('php', 'composer')) {
        Get-Command $commandName -CommandType Application -ErrorAction Stop | Out-Null
    }
    foreach ($requiredFile in @('.env', 'artisan', 'composer.json', 'composer.lock', 'vendor/autoload.php')) {
        if (-not (Test-Path -LiteralPath $requiredFile -PathType Leaf)) {
            throw "Required file not found: $requiredFile. Install local dependencies before preparing deployment."
        }
    }

    Write-Host '[2/6] Clearing local caches...' -ForegroundColor Yellow
    Invoke-DeployCommand -Command 'php' -Arguments @('artisan', 'optimize:clear')

    Write-Host '[3/6] Installing production dependencies...' -ForegroundColor Yellow
    Invoke-DeployCommand -Command 'composer' -Arguments @('install', '--no-dev', '--optimize-autoloader', '--no-interaction')

    Write-Host '[4/6] Checking routes and clearing caches for upload...' -ForegroundColor Yellow
    Invoke-DeployCommand -Command 'php' -Arguments @('artisan', 'route:list')
    Invoke-DeployCommand -Command 'php' -Arguments @('artisan', 'optimize:clear')

    Write-Host '[5/6] Preparing deploy folder...' -ForegroundColor Yellow
    if (-not (Test-Path -LiteralPath 'deploy' -PathType Container)) {
        New-Item -ItemType Directory -Path 'deploy' -ErrorAction Stop | Out-Null
    }

    Write-Host '[6/6] Preparing production environment template...' -ForegroundColor Yellow
    $templatePath = Join-Path $PSScriptRoot 'deploy/.env.production'
    if (Test-Path -LiteralPath $templatePath) {
        Write-Host 'Existing deploy/.env.production preserved. Check its settings before use.' -ForegroundColor Yellow
    }
    else {
        $envContent = @'
APP_NAME="B2B Marketplace"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com

APP_LOCALE=fa
APP_TIMEZONE=Asia/Tehran

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
'@
        [System.IO.File]::WriteAllText($templatePath, $envContent, [System.Text.UTF8Encoding]::new($false))
    }

    Write-Host ''
    Write-Host 'Preparation Complete!' -ForegroundColor Green
    Write-Host 'Create the deployment ZIP manually from the application files and production vendor folder.'
    Write-Host 'Include built frontend assets in public/build; this script does not build frontend assets.'
    Write-Host 'Exclude .git, node_modules, tests, local .env, logs, backups and local cache files.'
    Write-Host 'The environment template is in deploy/.env.production; it is not a complete deployment archive.'
    Write-Host ''
    Write-Host 'On existing hosting, preserve the current .env, APP_KEY and uploaded files.' -ForegroundColor Yellow
    Write-Host 'For a new installation only, configure the template and generate an APP_KEY on the server.'
    Write-Host 'Point the web document root to the application public directory.'
    Write-Host 'After uploading, run on the server:'
    Write-Host '  php artisan optimize:clear'
    Write-Host '  php artisan migrate --force'
    Write-Host '  php artisan optimize'
}
catch {
    $deployExitCode = 1
    Write-Host ''
    Write-Host "Preparation failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host 'Deployment preparation is incomplete. Fix the error before uploading.' -ForegroundColor Yellow
}
finally {
    try {
        if ($locationPushed) {
            Pop-Location
        }
    }
    finally {
        $ErrorActionPreference = $previousErrorActionPreference
        if (-not $NoPause) {
            Read-Host 'Press Enter to close' | Out-Null
        }
    }
}

exit $deployExitCode
