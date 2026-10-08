<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
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
    public const QUALITY = 82;

    /**
     * Generate the public product image and its variants.
     *
     * The optional base name allows staging workflows to use collision-free
     * names while preserving the existing slug/index API for current callers.
     */
    public function process(
        string $sourcePath,
        string $slug,
        int $index,
        bool $withPdf = false,
        ?string $baseName = null,
    ): array {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new InvalidArgumentException('فایل تصویر ورودی قابل خواندن نیست.');
        }

        $filename = $baseName ?? "{$slug}-{$index}";
        $this->assertSafeBaseName($filename);

        $baseDir = storage_path('app/public/products/gallery');
        $manager = ImageManager::gd();
        $this->ensureDir($baseDir);

        $mainPath = "{$baseDir}/{$filename}.webp";
        $manager->read($sourcePath)
            ->scaleDown(width: self::MAIN_WIDTH)
            ->toWebp(quality: self::QUALITY)
            ->save($mainPath);

        foreach (self::THUMBNAIL_SIZES as $directory => $width) {
            $variantDir = "{$baseDir}/{$directory}";
            $this->ensureDir($variantDir);

            $manager->read($sourcePath)
                ->scaleDown(width: $width)
                ->toWebp(quality: self::QUALITY)
                ->save("{$variantDir}/{$filename}.webp");
        }

        if ($withPdf) {
            $pdfDir = "{$baseDir}/pdf";
            $this->ensureDir($pdfDir);

            $manager->read($sourcePath)
                ->scaleDown(width: self::PDF_WIDTH)
                ->toJpeg(quality: self::QUALITY)
                ->save("{$pdfDir}/{$filename}.jpg");
        }

        $mainImage = $manager->read($mainPath);

        return [
            'base_name' => $filename,
            'file_name' => "{$filename}.webp",
            'file_path' => "products/gallery/{$filename}.webp",
            'file_size' => (int) filesize($mainPath),
            'mime_type' => 'image/webp',
            'width' => $mainImage->width(),
            'height' => $mainImage->height(),
            'versions' => $this->buildVersions($filename, $withPdf),
        ];
    }

    public function deleteProcessed(array $metadata): void
    {
        $paths = [];

        if (isset($metadata['file_path']) && is_string($metadata['file_path'])) {
            $paths[] = $metadata['file_path'];
        }

        foreach (($metadata['versions'] ?? []) as $version) {
            $path = is_array($version) ? ($version['path'] ?? null) : $version;

            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        Storage::disk('public')->delete(array_values(array_unique($paths)));
    }

    private function buildVersions(string $filename, bool $withPdf): array
    {
        $versions = [];
        $baseDir = storage_path('app/public/products/gallery');

        foreach (self::THUMBNAIL_SIZES as $directory => $requestedWidth) {
            $path = "products/gallery/{$directory}/{$filename}.webp";
            $dimensions = @getimagesize("{$baseDir}/{$directory}/{$filename}.webp");
            $versions[$directory] = [
                'path' => $path,
                'width' => (int) ($dimensions[0] ?? $requestedWidth),
                'height' => isset($dimensions[1]) ? (int) $dimensions[1] : null,
                'mime' => 'image/webp',
            ];
        }

        $mainDimensions = @getimagesize("{$baseDir}/{$filename}.webp");
        $versions['original'] = [
            'path' => "products/gallery/{$filename}.webp",
            'width' => (int) ($mainDimensions[0] ?? self::MAIN_WIDTH),
            'height' => isset($mainDimensions[1]) ? (int) $mainDimensions[1] : null,
            'mime' => 'image/webp',
        ];

        if ($withPdf) {
            $pdfDimensions = @getimagesize("{$baseDir}/pdf/{$filename}.jpg");
            $versions['pdf'] = [
                'path' => "products/gallery/pdf/{$filename}.jpg",
                'width' => (int) ($pdfDimensions[0] ?? self::PDF_WIDTH),
                'height' => isset($pdfDimensions[1]) ? (int) $pdfDimensions[1] : null,
                'mime' => 'image/jpeg',
            ];
        }

        return $versions;
    }

    private function assertSafeBaseName(string $filename): void
    {
        if ($filename === '' || preg_match('/^[\p{L}\p{N}_-]+$/u', $filename) !== 1) {
            throw new InvalidArgumentException('نام پایه تصویر نامعتبر است.');
        }
    }

    private function ensureDir(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new InvalidArgumentException("امکان ایجاد پوشه تصویر وجود ندارد: {$path}");
        }
    }
}
