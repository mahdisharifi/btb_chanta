#!/bin/bash

# تست همزمان با cURL
# این اسکریپت را روی سرور اجرا کنید

# تنظیمات
BASE_URL="https://your-domain.com"
USERS=5
DELAY=0.5

echo "🚀 شروع تست همزمان با $USERS کاربر..."

# ایجاد آرایه‌ای از PID ها
pids=()

# حلقه برای ایجاد درخواست‌های همزمان
for i in $(seq 1 $USERS); do
    echo "📊 کاربر $i شروع کرد..."
    
    # اجرای درخواست در background
    curl -X GET "$BASE_URL/supplier/catalog/preview/1" \
         -H "Cookie: laravel_session=YOUR_SESSION_HERE" \
         -s -o /dev/null &
    
    # ذخیره PID
    pids+=($!)
    
    # تاخیر کوتاه
    sleep $DELAY
done

# منتظر تمام شدن همه درخواست‌ها
echo "⏳ منتظر اتمام همه درخواست‌ها..."
for pid in "${pids[@]}"; do
    wait $pid
done

echo "✅ تست تمام شد!"

# نمایش آمار
echo ""
echo "📊 آمار سرور:"
echo "Total Requests: $USERS"
echo "Concurrent: Yes"
echo "Delay: ${DELAY}s between requests"
