<?php

namespace App\Services;

use Intervention\Image\ImageManager;

class ProductImageService
{
    public const THUMBNAIL_SIZES = [
        'xs' => 150,
        'sm' => 300,
        'md' => 600,
        'lg' => 900,
        'xl' => 1200,
    ];

    public const MAIN_WIDTH = 1200;
    public const PDF_WIDTH = 400;
    public const QUALITY = 82; // تعادل خوب بین کیفیت و حجم

    /**
     * پردازش کامل یک تصویر:
     * - WebP اصلی
     * - سایزهای xs تا xl
     * - JPG برای PDF
     * خروجی: متادیتای فایل اصلی برای درج در product_galleries
     */
    public function process(string $sourcePath, string $slug, int $index, bool $withPdf = false): array
    {
        $baseDir  = storage_path('app/public/products/gallery');
        $filename = "{$slug}-{$index}";
        $manager  = ImageManager::gd();

        $this->ensureDir($baseDir);

        // 1) تصویر اصلی WebP
        $mainPath = "{$baseDir}/{$filename}.webp";
        if (!file_exists($mainPath)) {
            $manager->read($sourcePath)
                ->scaleDown(width: self::MAIN_WIDTH)
                ->toWebp(quality: self::QUALITY)
                ->save($mainPath);
        }

        // 2) نسخه‌های ریسپانسیو
        foreach (self::THUMBNAIL_SIZES as $dir => $width) {
            $variantDir  = "{$baseDir}/{$dir}";
            $variantPath = "{$variantDir}/{$filename}.webp";

            if (!file_exists($variantPath)) {
                $this->ensureDir($variantDir);

                $manager->read($sourcePath)
                    ->scaleDown(width: $width)
                    ->toWebp(quality: self::QUALITY)
                    ->save($variantPath);
            }
        }

        // 3) JPG برای PDF
        if ($withPdf) {
            $pdfDir  = "{$baseDir}/pdf";
            $pdfPath = "{$pdfDir}/{$filename}.jpg";

            if (!file_exists($pdfPath)) {
                $this->ensureDir($pdfDir);

                $manager->read($sourcePath)
                    ->scaleDown(width: self::PDF_WIDTH)
                    ->toJpeg(quality: self::QUALITY)
                    ->save($pdfPath);
            }
        }

        // ابعاد از فایل اصلی ذخیره‌شده
        $img = $manager->read($mainPath);

        return [
            'base_name'=>$filename,
            'file_name'=>"{$filename}.webp",
            'file_path' => "products/gallery/{$filename}.webp",
            'file_size' => filesize($mainPath),
            'mime_type' => 'image/webp',
            'width'     => $img->width(),
            'height'    => $img->height(),
            'versions'  => $this->buildVersions($filename),
        ];
    }

    private function buildVersions(string $filename): array
    {
        $versions = [];

        foreach (self::THUMBNAIL_SIZES as $dir => $width) {
            $versions[$dir] = [
                'path'  => "products/gallery/{$dir}/{$filename}.webp",
                'width' => $width,
                'mime'  => 'image/webp',
            ];
        }

        $versions['original'] = [
            'path'  => "products/gallery/{$filename}.webp",
            'width' => self::MAIN_WIDTH,
            'mime'  => 'image/webp',
        ];

        $versions['pdf'] = [
            'path'  => "products/gallery/pdf/{$filename}.jpg",
            'width' => self::PDF_WIDTH,
            'mime'  => 'image/jpeg',
        ];

        return $versions;
    }

    private function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
