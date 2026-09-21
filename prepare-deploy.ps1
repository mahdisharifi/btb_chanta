# اسکریپت آماده‌سازی برای استقرار در هاست
# اجرا روی سیستم لوکال قبل از آپلود

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  آماده‌سازی پروژه برای استقرار" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# مسیر پروژه
$projectPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $projectPath

# 1. بررسی وجود .env
Write-Host "[1/7] بررسی فایل .env..." -ForegroundColor Yellow
if (-Not (Test-Path ".env")) {
    Write-Host "⚠️ فایل .env وجود ندارد!" -ForegroundColor Red
    Write-Host "لطفاً ابتدا فایل .env را ایجاد کنید." -ForegroundColor Red
    exit 1
}

# 2. پاکسازی کش‌های قدیمی
Write-Host "[2/7] پاکسازی کش‌های قدیمی..." -ForegroundColor Yellow
php artisan optimize:clear
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ خطا در پاکسازی کش" -ForegroundColor Red
    exit 1
}
Write-Host "✅ کش‌ها پاکسازی شدند" -ForegroundColor Green

# 3. نصب dependencies
Write-Host "[3/7] نصب dependencies بدون dev..." -ForegroundColor Yellow
composer install --no-dev --optimize-autoloader
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ خطا در نصب dependencies" -ForegroundColor Red
    exit 1
}
Write-Host "✅ Dependencies نصب شدند" -ForegroundColor Green

# 4. کش کردن تنظیمات
Write-Host "[4/7] کش کردن تنظیمات..." -ForegroundColor Yellow
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
Write-Host "✅ تنظیمات کش شدند" -ForegroundColor Green

# 5. بررسی خطاها
Write-Host "[5/7] بررسی خطاها..." -ForegroundColor Yellow
php artisan route:list | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ خطا در روت‌ها" -ForegroundColor Red
    exit 1
}
Write-Host "✅ روت‌ها سالم هستند" -ForegroundColor Green

# 6. ایجاد آرشیو
Write-Host "[6/7] ایجاد آرشیو برای انتقال..." -ForegroundColor Yellow

# لیست پوشه‌ها و فایل‌های مستثنی
$excludeList = @(
    ".git",
    "node_modules",
    "tests",
    ".phpunit.result.cache",
    "*.log",
    "storage/logs/*",
    "storage/framework/cache/*",
    ".env",
    ".env.example",
    ".env.testing",
    "DEPLOYMENT_CHECKLIST.md",
    "DEPLOY_COMMANDS.md",
    "prepare-deploy.ps1"
)

# ایجاد پوشه deploy اگر وجود ندارد
if (-Not (Test-Path "deploy")) {
    New-Item -ItemType Directory -Path "deploy" | Out-Null
}

# ایجاد آرشیو با Compress-Archive
Write-Host "   فشرده‌سازی فایل‌ها (ممکن است زمان‌بر باشد)..." -ForegroundColor Gray

# حذف آرشیو قبلی
if (Test-Path "deploy\project.zip") {
    Remove-Item "deploy\project.zip" -Force
}

# فشرده‌سازی (Compress-Archive نمی‌تواند exclude کند، پس از روش جایگزین استفاده می‌کنیم)
$filesToCompress = Get-ChildItem -Path . -Exclude @("node_modules", ".git", "tests", "deploy", "*.log")

# استفاده از 7zip اگر نصب باشد، وگرنه Compress-Archive
if (Get-Command "7z" -ErrorAction SilentlyContinue) {
    $excludeString = $excludeList -join " -x!"
    7z a -tzip deploy\project.zip * $excludeString
} else {
    # روش ساده‌تر: Compress-Archive همه فایل‌ها
    Compress-Archive -Path $filesToCompress -DestinationPath "deploy\project.zip" -CompressionLevel Optimal
    Write-Host "⚠️ فایل‌های زیر در آرشیو هستند، در هاست حذف کنید:" -ForegroundColor Yellow
    Write-Host "   - node_modules" -ForegroundColor Gray
    Write-Host "   - .git" -ForegroundColor Gray
    Write-Host "   - tests" -ForegroundColor Gray
}

Write-Host "✅ آرشیو ایجاد شد: deploy\project.zip" -ForegroundColor Green

# 7. ایجاد فایل .env نمونه
Write-Host "[7/7] ایجاد فایل .env نمونه برای هاست..." -ForegroundColor Yellow
$envContent = @"
APP_NAME="پلتفرم چن تا؟"
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
Write-Host "✅ فایل .env.production ایجاد شد" -ForegroundColor Green

# خلاصه
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  آماده‌سازی کامل شد!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "فایل‌های آماده شده در پوشه 'deploy':" -ForegroundColor White
Write-Host "  📦 project.zip" -ForegroundColor Yellow
Write-Host "  📄 .env.production" -ForegroundColor Yellow
Write-Host ""
Write-Host "مراحل بعدی:" -ForegroundColor White
Write-Host "1. فایل project.zip را در public_html هاست آپلود کنید" -ForegroundColor Gray
Write-Host "2. فایل را extract کنید" -ForegroundColor Gray
Write-Host "3. فایل .env.production را به .env تغییر نام دهید" -ForegroundColor Gray
Write-Host "4. تنظیمات دیتابیس را در .env وارد کنید" -ForegroundColor Gray
Write-Host "5. دستورات زیر را در SSH اجرا کنید:" -ForegroundColor Gray
Write-Host "   php artisan optimize:clear" -ForegroundColor Gray
Write-Host "   php artisan migrate --force" -ForegroundColor Gray
Write-Host "   php artisan optimize" -ForegroundColor Gray
Write-Host ""
