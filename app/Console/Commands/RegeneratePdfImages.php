<?php

namespace App\Console\Commands;

use App\Services\ProductImageService;
use Illuminate\Console\Command;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class RegeneratePdfImages extends Command
{
    protected $signature = 'images:regenerate-pdf
                            {--dry-run : فقط نمایش، بدون تغییر فایل}
                            {--force : بازتولید حتی اگر عرض فعلی <= 400 باشد}';

    protected $description = 'بازتولید تصاویر JPEG کاتالوگ PDF با عرض جدید PDF_WIDTH';

    public function handle(): int
    {
        $baseDir = storage_path('app/public/products/gallery');
        $pdfDir  = $baseDir . '/pdf';

        if (!is_dir($pdfDir)) {
            $this->error("مسیر یافت نشد: {$pdfDir}");
            return self::FAILURE;
        }

        $files = glob($pdfDir . '/*.jpg');
        $this->info('تعداد فایل‌ها: ' . count($files));

        $manager = new ImageManager(new Driver());
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        $done = 0; $skipped = 0; $failed = 0;

        foreach ($files as $file) {
            try {
                [$width] = getimagesize($file);

                // اگر از قبل کوچک است، رد شو (مگر با force)
                if ($width <= ProductImageService::PDF_WIDTH && !$this->option('force')) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                if ($this->option('dry-run')) {
                    $done++;
                    $bar->advance();
                    continue;
                }

                // اولویت: منبع WebP اصلی برای جلوگیری از افت کیفیت JPEG→JPEG
                $basename = pathinfo($file, PATHINFO_FILENAME);
                $webpSource = $baseDir . '/' . $basename . '.webp';
                $source = is_file($webpSource) ? $webpSource : $file;

                $manager->read($source)
                    ->scaleDown(width: ProductImageService::PDF_WIDTH)
                    ->toJpeg(ProductImageService::QUALITY)
                    ->save($file);

                $done++;
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn(basename($file) . ' → ' . $e->getMessage());
            }

            $bar->advance();

            // آزادسازی حافظه در هاست اشتراکی
            if (($done % 50) === 0) {
                gc_collect_cycles();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->table(
            ['بازتولید شد', 'رد شد (کوچک بود)', 'خطا'],
            [[$done, $skipped, $failed]]
        );

        return self::SUCCESS;
    }
}
