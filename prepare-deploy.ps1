# Laravel Deployment Preparation Script
# Run on local machine before upload

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Preparing Project for Deployment" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Project path
$projectPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $projectPath

# 1. Check .env file
Write-Host "[1/7] Checking .env file..." -ForegroundColor Yellow
if (-Not (Test-Path ".env")) {
    Write-Host "ERROR: .env file not found!" -ForegroundColor Red
    exit 1
}

# 2. Clear old caches
Write-Host "[2/7] Clearing old caches..." -ForegroundColor Yellow
php artisan optimize:clear
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Failed to clear caches" -ForegroundColor Red
    exit 1
}
Write-Host "Caches cleared successfully" -ForegroundColor Green

# 3. Install dependencies
Write-Host "[3/7] Installing dependencies (no dev)..." -ForegroundColor Yellow
composer install --no-dev --optimize-autoloader
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Failed to install dependencies" -ForegroundColor Red
    exit 1
}
Write-Host "Dependencies installed successfully" -ForegroundColor Green

# 4. Cache settings
Write-Host "[4/7] Caching settings..." -ForegroundColor Yellow
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
Write-Host "Settings cached successfully" -ForegroundColor Green

# 5. Check for errors
Write-Host "[5/7] Checking for errors..." -ForegroundColor Yellow
php artisan route:list | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Routes have errors" -ForegroundColor Red
    exit 1
}
Write-Host "Routes are healthy" -ForegroundColor Green

# 6. Create deploy folder
Write-Host "[6/7] Creating deploy folder..." -ForegroundColor Yellow
if (-Not (Test-Path "deploy")) {
    New-Item -ItemType Directory -Path "deploy" | Out-Null
}
Write-Host "Deploy folder created" -ForegroundColor Green

# 7. Create .env.production template
Write-Host "[7/7] Creating .env.production template..." -ForegroundColor Yellow

$envContent = @"
APP_NAME="B2B Marketplace"
APP_ENV=production
APP_KEY=base64:ENW44uDQGJ8dl4fv9FYDRe2DBgdlJpgtIyUoZLhQ9HA=
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
"@

Set-Content -Path "deploy\.env.production" -Value $envContent -Encoding UTF8
Write-Host ".env.production template created" -ForegroundColor Green

# Summary
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Preparation Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Files ready in 'deploy' folder:" -ForegroundColor White
Write-Host "  - .env.production (settings template)" -ForegroundColor Yellow
Write-Host ""
Write-Host "To create ZIP file manually:" -ForegroundColor White
Write-Host "1. Select these folders:" -ForegroundColor Gray
Write-Host "   app, bootstrap, config, database, lang, public, resources, routes, storage, vendor" -ForegroundColor Gray
Write-Host "   Files: artisan, composer.json, composer.lock" -ForegroundColor Gray
Write-Host "2. Exclude these folders:" -ForegroundColor Gray
Write-Host "   .git, node_modules, tests, deploy" -ForegroundColor Gray
Write-Host ""
Write-Host "Next steps:" -ForegroundColor White
Write-Host "1. Upload ZIP to public_html on hosting" -ForegroundColor Gray
Write-Host "2. Extract the ZIP file" -ForegroundColor Gray
Write-Host "3. Rename .env.production to .env" -ForegroundColor Gray
Write-Host "4. Update database settings in .env" -ForegroundColor Gray
Write-Host "5. Run these commands via SSH:" -ForegroundColor Gray
Write-Host "   php artisan optimize:clear" -ForegroundColor Gray
Write-Host "   php artisan migrate --force" -ForegroundColor Gray
Write-Host "   php artisan optimize" -ForegroundColor Gray
Write-Host ""
