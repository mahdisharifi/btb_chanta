<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Intervention\Image\ImageManager;

class OptimizeImages extends Command
{
    protected $signature = 'images:optimize
                            {--source= : مسیر پوشه مبدأ (پیش‌فرض: storage/app/public/products/change-image)}
                            {--width=600 : حداکثر عرض تصویر}
                            {--quality=85 : کیفیت خروجی (پیشنهاد گوگل: ۷۵ تا ۸۵)}
                            {--min-size=0 : حداقل حجم فایل به کیلوبایت برای پردازش (0 = همه)}
                            {--avif-width=300 : عرض خروجی AVIF}';


    protected $description = 'بهینه‌سازی تصاویر: نسخه اصلی فشرده‌شده + نسخه WebP — بدون تغییر نام فایل';

    /** پسوندهای مجاز ورودی */
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

    public function handle(): int
    {
        $sourceDir = $this->option('source')
            ?: storage_path('app/public/products/change-image');

        $width   = (int) $this->option('width');
        $avifwidth   = (int) $this->option('avif-width');
        $quality = (int) $this->option('quality');
        $minSize = (int) $this->option('min-size') * 1024; // به بایت

        if (! is_dir($sourceDir)) {
            $this->error("❌ پوشه یافت نشد: {$sourceDir}");
            return self::FAILURE;
        }

        $webpOutput = $sourceDir . DIRECTORY_SEPARATOR . 'optimized webp';
        $outputDir = $sourceDir . DIRECTORY_SEPARATOR . 'optimized jpg';
        $outputDir_old = $sourceDir . DIRECTORY_SEPARATOR . 'optimized old';

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        if (! is_dir($outputDir_old)) {
            mkdir($outputDir_old, 0755, true);
        }



        $files = collect(scandir($sourceDir))
            ->filter(fn ($f) => in_array(
                strtolower(pathinfo($f, PATHINFO_EXTENSION)),
                $this->allowedExtensions
            ))
            ->values();

        if ($files->isEmpty()) {
            $this->warn('⚠️ هیچ تصویری در پوشه یافت نشد.');
            return self::SUCCESS;
        }

        $this->info("📁 مبدأ:  {$sourceDir}");
        $this->info("📁 مقصد: {$outputDir}");
        $this->info("🖼  تعداد تصاویر: {$files->count()}");
        $this->newLine();

        $manager = ImageManager::gd();
        $bar     = $this->output->createProgressBar($files->count());
        $bar->start();

        $converted  = 0;
        $skipped    = 0;
        $failed     = 0;
        $savedBytes = 0;

        foreach ($files as $file) {
            $sourcePath   = $sourceDir . DIRECTORY_SEPARATOR . $file;
            $originalSize = filesize($sourcePath);

            // رد کردن فایل‌های کوچک‌تر از حد تعیین‌شده
            if ($minSize > 0 && $originalSize < $minSize) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $baseName  = pathinfo($file, PATHINFO_FILENAME);
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            try {
                // یک بار خواندن و ریسایز — دو خروجی
                $image = $manager->read($sourcePath)
                    ->scaleDown(width: $width);


                $outputPath = $outputDir . DIRECTORY_SEPARATOR . $baseName . '.jpg';
                $image->scaleDown(width: $width)
                    ->toJpeg(quality: $quality)
                    ->save($outputPath);

                $this->line("  ✓ {$baseName}.jpg");

                // ۱) نسخه با فرمت و نام اصلی — فشرده‌شده
                $originalOutput = $outputDir_old . DIRECTORY_SEPARATOR . $file;
                $image->encodeByExtension($extension, quality: $quality)

                    ->save($originalOutput);

                // ۲) نسخه WebP (پیشنهاد گوگل)
                $webpOutput = $outputDir . DIRECTORY_SEPARATOR . $baseName . '.webp';
                $image->toWebp(quality: $quality)
                    ->save($webpOutput);


                $savedBytes += max(0, $originalSize - filesize($originalOutput));
                $converted++;
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("❌ خطا در «{$file}»: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['وضعیت', 'تعداد'],
            [
                ['✅ پردازش‌شده (۲ خروجی)', $converted],
                ['⏭ رد‌شده (حجم کم)', $skipped],
                ['❌ خطا', $failed],
            ]
        );

        $savedMb = round($savedBytes / 1024 / 1024, 2);
        $this->info("💾 مجموع کاهش حجم (نسبت به فایل اصلی): {$savedMb} مگابایت");

        return self::SUCCESS;
    }
}
