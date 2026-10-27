<?php

/**
 * اسکریپت بهینه‌سازی تصاویر PNG با حفظ کیفیت
 * 
 * استفاده: php scripts/optimize-image.php
 */

$sourceFile = 'public/images/icons/warranty-shield-original.png';
$destFile = 'public/images/icons/warranty-shield.png';
$targetWidth = 50;
$targetHeight = 50;

echo "🔍 بررسی فایل منبع...\n";

if (!file_exists($sourceFile)) {
    die("❌ فایل منبع یافت نشد: $sourceFile\n");
}

// دریافت اطلاعات تصویر
$imageInfo = getimagesize($sourceFile);
if (!$imageInfo) {
    die("❌ 无法读取图片信息\n");
}

$originalWidth = $imageInfo[0];
$originalHeight = $imageInfo[1];
$originalSize = filesize($sourceFile);

echo "📐 ابعاد اصلی: {$originalWidth}x{$originalHeight} پیکسل\n";
echo "📦 حجم اصلی: " . round($originalSize / 1024, 2) . " کیلوبایت\n";

// بارگذاری تصویر
$sourceImage = imagecreatefrompng($sourceFile);
if (!$sourceImage) {
    die("❌ 无法加载图片\n");
}

// فعال‌سازی alpha blending
imagealphablending($sourceImage, true);
imagesavealpha($sourceImage, true);

// ایجاد تصویر جدید
$destImage = imagecreatetruecolor($targetWidth, $targetHeight);
if (!$destImage) {
    die("❌ 无法创建新图片\n");
}

// تنظیم شفافیت
imagealphablending($destImage, false);
imagesavealpha($destImage, true);
$transparent = imagecolorallocatealpha($destImage, 0, 0, 0, 127);
imagefilledrectangle($destImage, 0, 0, $targetWidth, $targetHeight, $transparent);
imagealphablending($destImage, true);

// تغییر سایز با کیفیت بالا
imagecopyresampled(
    $destImage, 
    $sourceImage, 
    0, 0, 0, 0, 
    $targetWidth, 
    $targetHeight, 
    $originalWidth, 
    $originalHeight
);

// ذخیره تصویر
$success = imagepng($destImage, $destFile, 9, PNG_ALL_FILTERS);

if (!$success) {
    die("❌ ذخیره تصویر ناموفق بود\n");
}

// آزادسازی حافظه
imagedestroy($sourceImage);
imagedestroy($destImage);

// نمایش نتیجه
$newSize = filesize($destFile);
$reduction = round((1 - ($newSize / $originalSize)) * 100, 2);

echo "\n✅ تصویر با موفقیت بهینه شد!\n";
echo "📐 ابعاد جدید: {$targetWidth}x{$targetHeight} پیکسل\n";
echo "📦 حجم جدید: " . round($newSize / 1024, 2) . " کیلوبایت\n";
echo "📊 کاهش حجم: {$reduction}%\n";
echo "📍 فایل خروجی: $destFile\n";
