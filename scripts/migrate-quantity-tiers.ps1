# ═══════════════════════════════════════════════════════════════
# 🚀 اسکریپت مهاجرت تیراژبندی خودکار - PowerShell
# ═══════════════════════════════════════════════════════════════

$ErrorActionPreference = "Stop"

# تابع رنگی
function Write-Info { Write-Host "ℹ️  $args" -ForegroundColor Blue }
function Write-Success { Write-Host "✅ $args" -ForegroundColor Green }
function Write-Warning { Write-Host "⚠️  $args" -ForegroundColor Yellow }
function Write-Error-Custom { Write-Host "❌ $args" -ForegroundColor Red }

function Confirm-Action {
    param([string]$Message)
    Write-Warning "$Message (Y/N): "
    $response = Read-Host
    return $response -eq 'Y' -or $response -eq 'y'
}

# ═══════════════════════════════════════════════════════════════
# مرحله 0: مقدمه
# ═══════════════════════════════════════════════════════════════

Clear-Host
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "    🚀 اسکریپت مهاجرت تیراژبندی خودکار" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""
Write-Info "این اسکریپت مراحل زیر را انجام می‌دهد:"
Write-Host "  1. پشتیبان‌گیری از دیتابیس و کد"
Write-Host "  2. Import دیتابیس اصلی"
Write-Host "  3. بررسی سلامت دیتابیس"
Write-Host "  4. اجرای migration تیراژبندی"
Write-Host "  5. Verification و تست"
Write-Host ""
Write-Warning "⏱️  زمان تخمینی: 10-15 دقیقه"
Write-Host ""

if (-not (Confirm-Action "آیا می‌خواهید ادامه دهید؟")) {
    Write-Error-Custom "لغو شد توسط کاربر"
    exit 1
}

# ═══════════════════════════════════════════════════════════════
# مرحله 1: پشتیبان‌گیری
# ═══════════════════════════════════════════════════════════════

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  📦 مرحله 1: پشتیبان‌گیری" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

# پشتیبان Git
Write-Info "پشتیبان‌گیری از کد..."
git add .
git commit -m "Backup before quantity tier migration - $(Get-Date -Format 'yyyyMMdd_HHmmss')"
$branchName = "backup-before-migration-$(Get-Date -Format 'yyyyMMdd_HHmmss')"
git branch $branchName
Write-Success "کد پشتیبان شد (برنچ: $branchName)"

# پشتیبان دیتابیس
Write-Info "پشتیبان‌گیری از دیتابیس..."
$backupFile = "backup_before_migration_$(Get-Date -Format 'yyyyMMdd_HHmmss').sql"

$dbUser = Read-Host "نام کاربر MySQL [root]"
if ([string]::IsNullOrEmpty($dbUser)) { $dbUser = "root" }

$securePassword = Read-Host "رمز عبور MySQL" -AsSecureString
$dbPassword = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword))

$dbName = Read-Host "نام دیتابیس [b2b_marketplace_server]"
if ([string]::IsNullOrEmpty($dbName)) { $dbName = "b2b_marketplace_server" }

try {
    mysqldump -u $dbUser -p"$dbPassword" $dbName | Out-File -FilePath $backupFile -Encoding UTF8
    $backupSize = (Get-Item $backupFile).Length / 1MB
    Write-Success "دیتابیس پشتیبان شد (حجم: $([math]::Round($backupSize, 2)) MB)"
    Write-Info "فایل backup: $backupFile"
}
catch {
    Write-Error-Custom "خطا در پشتیبان‌گیری دیتابیس"
    exit 1
}

# ═══════════════════════════════════════════════════════════════
# مرحله 2: Import دیتابیس اصلی
# ═══════════════════════════════════════════════════════════════

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  📥 مرحله 2: Import دیتابیس اصلی" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

Write-Warning "آیا می‌خواهید دیتابیس اصلی را import کنید؟"
Write-Warning "این عملیات دیتابیس فعلی را حذف می‌کند!"
Write-Host ""

if (Confirm-Action "ادامه می‌دهید؟") {
    $sqlFile = Read-Host "مسیر فایل SQL دیتابیس اصلی"
    
    if (-not (Test-Path $sqlFile)) {
        Write-Error-Custom "فایل $sqlFile یافت نشد"
        exit 1
    }
    
    try {
        Write-Info "حذف دیتابیس فعلی..."
        mysql -u $dbUser -p"$dbPassword" -e "DROP DATABASE IF EXISTS $dbName;"
        
        Write-Info "ایجاد دیتابیس جدید..."
        mysql -u $dbUser -p"$dbPassword" -e "CREATE DATABASE $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        
        Write-Info "Import دیتابیس اصلی (ممکن است چند دقیقه طول بکشد)..."
        Get-Content $sqlFile | mysql -u $dbUser -p"$dbPassword" $dbName
        
        Write-Success "دیتابیس اصلی با موفقیت import شد"
    }
    catch {
        Write-Error-Custom "خطا در import دیتابیس"
        Write-Error-Custom "Rollback به دیتابیس قبلی..."
        
        mysql -u $dbUser -p"$dbPassword" -e "DROP DATABASE IF EXISTS $dbName;"
        mysql -u $dbUser -p"$dbPassword" -e "CREATE DATABASE $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        Get-Content $backupFile | mysql -u $dbUser -p"$dbPassword" $dbName
        
        exit 1
    }
}
else {
    Write-Info "از دیتابیس فعلی استفاده می‌شود"
}

# ═══════════════════════════════════════════════════════════════
# مرحله 3: بررسی سلامت دیتابیس
# ═══════════════════════════════════════════════════════════════

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  🔍 مرحله 3: بررسی سلامت دیتابیس" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

Write-Info "در حال بررسی سلامت محصولات..."
php artisan products:check-health

Write-Host ""
if (-not (Confirm-Action "آیا نتایج بالا قابل قبول است؟ ادامه می‌دهید؟")) {
    Write-Error-Custom "لغو شد توسط کاربر"
    Write-Info "لطفاً محصولات مشکل‌دار را اصلاح کنید و دوباره اجرا کنید"
    exit 1
}

# ═══════════════════════════════════════════════════════════════
# مرحله 4: اصلاح محصولات مشکل‌دار (اختیاری)
# ═══════════════════════════════════════════════════════════════

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  🔧 مرحله 4: اصلاح محصولات مشکل‌دار (اختیاری)" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

if (Confirm-Action "آیا می‌خواهید محصولات بدون packaging_unit_type را اصلاح کنید؟") {
    Write-Info "تنظیم packaging_unit_type به 'carton'..."
    php artisan tinker --execute="`$count = DB::table('products')->whereNull('packaging_unit_type')->count(); DB::table('products')->whereNull('packaging_unit_type')->update(['packaging_unit_type' => 'carton']); echo 'تعداد محصولات اصلاح شده: ' . `$count;"
    Write-Success "محصولات اصلاح شدند"
}

if (Confirm-Action "آیا می‌خواهید محصولات با quantity_per_packaging نامعتبر را اصلاح کنید؟") {
    $defaultQty = Read-Host "مقدار پیش‌فرض برای quantity_per_packaging [100]"
    if ([string]::IsNullOrEmpty($defaultQty)) { $defaultQty = "100" }
    
    Write-Info "تنظیم quantity_per_packaging به $defaultQty..."
    php artisan tinker --execute="`$count = DB::table('products')->where('quantity_per_packaging', '<=', 0)->count(); DB::table('products')->where('quantity_per_packaging', '<=', 0)->update(['quantity_per_packaging' => $defaultQty]); echo 'تعداد محصولات اصلاح شده: ' . `$count;"
    Write-Success "محصولات اصلاح شدند"
}

# ═══════════════════════════════════════════════════════════════
# مرحله 5: پیش‌نمایش (Dry-Run)
# ═══════════════════════════════════════════════════════════════

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  👀 مرحله 5: پیش‌نمایش تغییرات (Dry-Run)" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

Write-Info "در حال اجرای Dry-Run..."
php artisan products:generate-tiers --dry-run | Select-Object -Last 20

Write-Host ""
if (-not (Confirm-Action "آیا نتایج بالا قابل قبول است؟ ادامه به اجرای واقعی؟")) {
    Write-Error-Custom "لغو شد توسط کاربر"
    exit 1
}

# ═══════════════════════════════════════════════════════════════
# مرحله 6: اجرای Migration
# ═══════════════════════════════════════════════════════════════

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  🚀 مرحله 6: اجرای Migration" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

Write-Warning "⚠️  شما در حال اعمال تغییرات دائمی روی دیتابیس هستید!"
if (-not (Confirm-Action "آیا مطمئن هستید؟")) {
    Write-Error-Custom "لغو شد توسط کاربر"
    exit 1
}

Write-Info "در حال اجرای Migration..."
$startTime = Get-Date
php artisan products:generate-tiers
$endTime = Get-Date
$duration = ($endTime - $startTime).TotalSeconds

Write-Success "Migration با موفقیت انجام شد (مدت زمان: $([math]::Round($duration, 2))s)"

# ═══════════════════════════════════════════════════════════════
# مرحله 7: Verification
# ═══════════════════════════════════════════════════════════════

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  ✅ مرحله 7: بررسی نتایج" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

Write-Info "بررسی آمار نهایی..."
php artisan tinker --execute="echo '═══════════════════════════════════════' . PHP_EOL; echo '📊 آمار نهایی دیتابیس' . PHP_EOL; echo '═══════════════════════════════════════' . PHP_EOL; echo 'کل محصولات: ' . App\Models\Product::count() . PHP_EOL; echo 'محصولات با تیراژ: ' . App\Models\Product::whereNotNull('quantity_tiers')->count() . PHP_EOL; echo 'محصولات بدون تیراژ: ' . App\Models\Product::whereNull('quantity_tiers')->orWhereJsonLength('quantity_tiers', 0)->count() . PHP_EOL; echo '═══════════════════════════════════════' . PHP_EOL;"

Write-Host ""
Write-Info "بررسی نمونه محصول..."
php artisan tinker --execute="`$product = App\Models\Product::whereNotNull('quantity_tiers')->first(); if (`$product) { echo 'نمونه: محصول #' . `$product->id . ' - ' . `$product->title . PHP_EOL; echo 'تعداد تیراژها: ' . count(`$product->quantity_tiers) . PHP_EOL; }"

# ═══════════════════════════════════════════════════════════════
# مرحله 8: گزارش نهایی
# ═══════════════════════════════════════════════════════════════

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  📝 گزارش نهایی" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

Write-Success "✅ Migration با موفقیت کامل شد!"
Write-Host ""
Write-Info "فایل Backup: $backupFile"
Write-Info "مدت زمان اجرا: $([math]::Round($duration, 2))s"
Write-Host ""
Write-Warning "⚠️  مراحل بعدی:"
Write-Host "  1. تست در مرورگر: php artisan serve"
Write-Host "  2. بروید به: http://127.0.0.1:8000/admin/products/create"
Write-Host "  3. محصول جدید ایجاد کنید و تیراژبندی را تست کنید"
Write-Host "  4. محصولات موجود را ویرایش کنید و تیراژها را بررسی کنید"
Write-Host ""
Write-Info "مستندات کامل: .kiro/docs/database-migration-quantity-tiers.md"
Write-Host ""
Write-Success "🎉 موفق باشید!"
Write-Host ""
