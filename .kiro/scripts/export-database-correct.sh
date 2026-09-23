#!/bin/bash
# اسکریپت export صحیح دیتابیس برای سرور

# تنظیمات دیتابیس (از .env خوانده می‌شود)
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_DATABASE="your_database_name"
DB_USERNAME="your_username"
DB_PASSWORD="your_password"

# نام فایل خروجی
OUTPUT_FILE="database_export_$(date +%Y%m%d_%H%M%S).sql"

# Export با سینتکس صحیح
mysqldump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --password="$DB_PASSWORD" \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  --add-drop-table \
  --create-options \
  --quote-names \
  --set-charset \
  --default-character-set=utf8mb4 \
  "$DB_DATABASE" > "$OUTPUT_FILE"

echo "✓ Database exported successfully to: $OUTPUT_FILE"
echo ""
echo "Next steps:"
echo "1. Upload $OUTPUT_FILE to your server"
echo "2. Import using: mysql -u username -p database_name < $OUTPUT_FILE"
