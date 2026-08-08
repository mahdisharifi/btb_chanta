#!/bin/bash

# تست با Apache Bench
# نصب: sudo apt-get install apache2-utils (Linux)
# یا: brew install httpd (macOS)

BASE_URL="https://your-domain.com"
ENDPOINT="/supplier/catalog/preview/1"
CONCURRENT_USERS=10
TOTAL_REQUESTS=50

echo "🚀 تست با Apache Bench..."
echo "📊 تنظیمات:"
echo "   Concurrent Users: $CONCURRENT_USERS"
echo "   Total Requests: $TOTAL_REQUESTS"
echo "   Endpoint: $ENDPOINT"
echo ""

# اجرای تست
ab -n $TOTAL_REQUESTS \
   -c $CONCURRENT_USERS \
   -H "Cookie: laravel_session=YOUR_SESSION_HERE" \
   "$BASE_URL$ENDPOINT"

echo ""
echo "✅ تست تمام شد!"
