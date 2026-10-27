#!/bin/bash

# ═══════════════════════════════════════════════════════════════
# 🚀 اسکریپت مهاجرت تیراژبندی خودکار
# ═══════════════════════════════════════════════════════════════

set -e  # توقف در صورت خطا

# رنگ‌ها
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# توابع کمکی
info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
}

confirm() {
    read -p "$(echo -e ${YELLOW}⚠️  $1 '(y/n): '${NC})" -n 1 -r
    echo
    [[ $REPLY =~ ^[Yy]$ ]]
}

# ═══════════════════════════════════════════════════════════════
# مرحله 0: مقدمه
# ═══════════════════════════════════════════════════════════════

clear
echo "═══════════════════════════════════════════════════════════════"
echo "    🚀 اسکریپت مهاجرت تیراژبندی خودکار"
echo "═══════════════════════════════════════════════════════════════"
echo ""
info "این اسکریپت مراحل زیر را انجام می‌دهد:"
echo "  1. پشتیبان‌گیری از دیتابیس و کد"
echo "  2. Import دیتابیس اصلی"
echo "  3. بررسی سلامت دیتابیس"
echo "  4. اجرای migration تیراژبندی"
echo "  5. Verification و تست"
echo ""
warning "⏱️  زمان تخمینی: 10-15 دقیقه"
echo ""

if ! confirm "آیا می‌خواهید ادامه دهید؟"; then
    error "لغو شد توسط کاربر"
    exit 1
fi

# ═══════════════════════════════════════════════════════════════
# مرحله 1: پشتیبان‌گیری
# ═══════════════════════════════════════════════════════════════

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  📦 مرحله 1: پشتیبان‌گیری"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# پشتیبان Git
info "پشتیبان‌گیری از کد..."
git add .
git commit -m "Backup before quantity tier migration - $(date +%Y%m%d_%H%M%S)" || true
git branch backup-before-migration-$(date +%Y%m%d_%H%M%S) || true
success "کد پشتیبان شد"

# پشتیبان دیتابیس
info "پشتیبان‌گیری از دیتابیس..."
BACKUP_FILE="backup_before_migration_$(date +%Y%m%d_%H%M%S).sql"

read -p "نام کاربر MySQL [root]: " DB_USER
DB_USER=${DB_USER:-root}

read -sp "رمز عبور MySQL: " DB_PASSWORD
echo ""

read -p "نام دیتابیس [b2b_marketplace_server]: " DB_NAME
DB_NAME=${DB_NAME:-b2b_marketplace_server}

mysqldump -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null || {
    error "خطا در پشتیبان‌گیری دیتابیس"
    exit 1
}

BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
success "دیتابیس پشتیبان شد (حجم: $BACKUP_SIZE)"
info "فایل backup: $BACKUP_FILE"

# ═══════════════════════════════════════════════════════════════
# مرحله 2: Import دیتابیس اصلی
# ═══════════════════════════════════════════════════════════════

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  📥 مرحله 2: Import دیتابیس اصلی"
echo "═══════════════════════════════════════════════════════════════"
echo ""

warning "آیا می‌خواهید دیتابیس اصلی را import کنید؟"
warning "این عملیات دیتابیس فعلی را حذف می‌کند!"
echo ""

if confirm "ادامه می‌دهید؟"; then
    read -p "مسیر فایل SQL دیتابیس اصلی: " SQL_FILE
    
    if [ ! -f "$SQL_FILE" ]; then
        error "فایل $SQL_FILE یافت نشد"
        exit 1
    fi
    
    info "حذف دیتابیس فعلی..."
    mysql -u "$DB_USER" -p"$DB_PASSWORD" -e "DROP DATABASE IF EXISTS $DB_NAME;" 2>/dev/null
    
    info "ایجاد دیتابیس جدید..."
    mysql -u "$DB_USER" -p"$DB_PASSWORD" -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
    
    info "Import دیتابیس اصلی (ممکن است چند دقیقه طول بکشد)..."
    mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$SQL_FILE" 2>/dev/null || {
        error "خطا در import دیتابیس"
        error "Rollback به دیتابیس قبلی..."
        mysql -u "$DB_USER" -p"$DB_PASSWORD" -e "DROP DATABASE IF EXISTS $DB_NAME;" 2>/dev/null
        mysql -u "$DB_USER" -p"$DB_PASSWORD" -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
        mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$BACKUP_FILE" 2>/dev/null
        exit 1
    }
    
    success "دیتابیس اصلی با موفقیت import شد"
else
    info "از دیتابیس فعلی استفاده می‌شود"
fi

# ═══════════════════════════════════════════════════════════════
# مرحله 3: بررسی سلامت دیتابیس
# ═══════════════════════════════════════════════════════════════

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  🔍 مرحله 3: بررسی سلامت دیتابیس"
echo "═══════════════════════════════════════════════════════════════"
echo ""

info "در حال بررسی سلامت محصولات..."
php artisan products:check-health

echo ""
warning "آیا نتایج بالا قابل قبول است؟"
if ! confirm "ادامه می‌دهید؟"; then
    error "لغو شد توسط کاربر"
    info "لطفاً محصولات مشکل‌دار را اصلاح کنید و دوباره اجرا کنید"
    exit 1
fi

# ═══════════════════════════════════════════════════════════════
# مرحله 4: اصلاح محصولات مشکل‌دار (اختیاری)
# ═══════════════════════════════════════════════════════════════

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  🔧 مرحله 4: اصلاح محصولات مشکل‌دار (اختیاری)"
echo "═══════════════════════════════════════════════════════════════"
echo ""

if confirm "آیا می‌خواهید محصولات بدون packaging_unit_type را اصلاح کنید؟"; then
    info "تنظیم packaging_unit_type به 'carton'..."
    php artisan tinker --execute="
        \$count = DB::table('products')->whereNull('packaging_unit_type')->count();
        DB::table('products')->whereNull('packaging_unit_type')->update(['packaging_unit_type' => 'carton']);
        echo 'تعداد محصولات اصلاح شده: ' . \$count;
    "
    success "محصولات اصلاح شدند"
fi

if confirm "آیا می‌خواهید محصولات با quantity_per_packaging نامعتبر را اصلاح کنید؟"; then
    read -p "مقدار پیش‌فرض برای quantity_per_packaging [100]: " DEFAULT_QTY
    DEFAULT_QTY=${DEFAULT_QTY:-100}
    
    info "تنظیم quantity_per_packaging به $DEFAULT_QTY..."
    php artisan tinker --execute="
        \$count = DB::table('products')->where('quantity_per_packaging', '<=', 0)->count();
        DB::table('products')->where('quantity_per_packaging', '<=', 0)->update(['quantity_per_packaging' => $DEFAULT_QTY]);
        echo 'تعداد محصولات اصلاح شده: ' . \$count;
    "
    success "محصولات اصلاح شدند"
fi

# ═══════════════════════════════════════════════════════════════
# مرحله 5: پیش‌نمایش (Dry-Run)
# ═══════════════════════════════════════════════════════════════

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  👀 مرحله 5: پیش‌نمایش تغییرات (Dry-Run)"
echo "═══════════════════════════════════════════════════════════════"
echo ""

info "در حال اجرای Dry-Run..."
php artisan products:generate-tiers --dry-run | tail -n 20

echo ""
warning "آیا نتایج بالا قابل قبول است؟"
if ! confirm "ادامه به اجرای واقعی؟"; then
    error "لغو شد توسط کاربر"
    exit 1
fi

# ═══════════════════════════════════════════════════════════════
# مرحله 6: اجرای Migration
# ═══════════════════════════════════════════════════════════════

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  🚀 مرحله 6: اجرای Migration"
echo "═══════════════════════════════════════════════════════════════"
echo ""

warning "⚠️  شما در حال اعمال تغییرات دائمی روی دیتابیس هستید!"
if ! confirm "آیا مطمئن هستید؟"; then
    error "لغو شد توسط کاربر"
    exit 1
fi

info "در حال اجرای Migration..."
START_TIME=$(date +%s)
php artisan products:generate-tiers
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

success "Migration با موفقیت انجام شد (مدت زمان: ${DURATION}s)"

# ═══════════════════════════════════════════════════════════════
# مرحله 7: Verification
# ═══════════════════════════════════════════════════════════════

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  ✅ مرحله 7: بررسی نتایج"
echo "═══════════════════════════════════════════════════════════════"
echo ""

info "بررسی آمار نهایی..."
php artisan tinker --execute="
echo '═══════════════════════════════════════' . PHP_EOL;
echo '📊 آمار نهایی دیتابیس' . PHP_EOL;
echo '═══════════════════════════════════════' . PHP_EOL;
echo 'کل محصولات: ' . App\Models\Product::count() . PHP_EOL;
echo 'محصولات با تیراژ: ' . App\Models\Product::whereNotNull('quantity_tiers')->count() . PHP_EOL;
echo 'محصولات بدون تیراژ: ' . App\Models\Product::whereNull('quantity_tiers')->orWhereJsonLength('quantity_tiers', 0)->count() . PHP_EOL;
echo '═══════════════════════════════════════' . PHP_EOL;
"

echo ""
info "بررسی نمونه محصول..."
php artisan tinker --execute="
\$product = App\Models\Product::whereNotNull('quantity_tiers')->first();
if (\$product) {
    echo 'نمونه: محصول #' . \$product->id . ' - ' . \$product->title . PHP_EOL;
    echo 'تعداد تیراژها: ' . count(\$product->quantity_tiers) . PHP_EOL;
}
"

# ═══════════════════════════════════════════════════════════════
# مرحله 8: گزارش نهایی
# ═══════════════════════════════════════════════════════════════

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  📝 گزارش نهایی"
echo "═══════════════════════════════════════════════════════════════"
echo ""

success "✅ Migration با موفقیت کامل شد!"
echo ""
info "فایل Backup: $BACKUP_FILE"
info "مدت زمان اجرا: ${DURATION}s"
echo ""
warning "⚠️  مراحل بعدی:"
echo "  1. تست در مرورگر: php artisan serve"
echo "  2. بروید به: http://127.0.0.1:8000/admin/products/create"
echo "  3. محصول جدید ایجاد کنید و تیراژبندی را تست کنید"
echo "  4. محصولات موجود را ویرایش کنید و تیراژها را بررسی کنید"
echo ""
info "مستندات کامل: .kiro/docs/database-migration-quantity-tiers.md"
echo ""
success "🎉 موفق باشید!"
echo ""
