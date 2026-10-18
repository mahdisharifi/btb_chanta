<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

class SupplierLogoImageService
{
    private const DISK = 'public';
    private const ROOT = 'suppliers/logos';
    private const WEBP_QUALITY = 82;
    private const PDF_WIDTH = 900;

    /** @return array{logo_path: string, logo_versions: array<string, array<string, mixed>>} */
    public function processUpload(UploadedFile $file): array
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'img');
        $sourcePath = self::ROOT.'/originals/'.Str::ulid().'.'.$extension;
        $disk = Storage::disk(self::DISK);
        $disk->put($sourcePath, file_get_contents($file->getRealPath()));

        try {
            return [
                'logo_path' => $sourcePath,
                'logo_versions' => $this->generateVersions($disk->path($sourcePath)),
            ];
        } catch (\Throwable $exception) {
            $disk->delete($sourcePath);
            throw $exception;
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function generateVersions(string $sourcePath): array
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new InvalidArgumentException('فایل لوگو قابل خواندن نیست.');
        }

        $id = (string) Str::ulid();
        $disk = Storage::disk(self::DISK);
        $baseDirectory = self::ROOT.'/variants/'.$id;
        $absoluteDirectory = $disk->path($baseDirectory);

        if (! is_dir($absoluteDirectory) && ! mkdir($absoluteDirectory, 0755, true) && ! is_dir($absoluteDirectory)) {
            throw new InvalidArgumentException('ساخت پوشه نسخه‌های لوگو ممکن نشد.');
        }

        $manager = ImageManager::gd();
        $sizes = [
            'thumbnail' => 96,
            'small' => 256,
            'medium' => 512,
        ];
        $versions = [];

        try {
            foreach ($sizes as $name => $width) {
                $relativePath = "{$baseDirectory}/{$name}.webp";
                $image = $manager->read($sourcePath)->scaleDown(width: $width);
                $image->toWebp(quality: self::WEBP_QUALITY)->save($disk->path($relativePath));
                $versions[$name] = [
                    'path' => $relativePath,
                    'width' => $image->width(),
                    'height' => $image->height(),
                    'mime' => 'image/webp',
                ];
            }

            // PNG keeps transparent marks intact and is supported by the catalog PDF renderers.
            $pdfPath = "{$baseDirectory}/pdf.png";
            $pdfImage = $manager->read($sourcePath)->scaleDown(width: self::PDF_WIDTH);
            $pdfImage->toPng()->save($disk->path($pdfPath));
            $versions['pdf'] = [
                'path' => $pdfPath,
                'width' => $pdfImage->width(),
                'height' => $pdfImage->height(),
                'mime' => 'image/png',
            ];

            return $versions;
        } catch (\Throwable $exception) {
            $disk->deleteDirectory($baseDirectory);
            throw $exception;
        }
    }

    public function delete(?string $logoPath, ?array $versions = null): void
    {
        $paths = array_filter([$logoPath]);
        foreach ($versions ?? [] as $version) {
            $path = is_array($version) ? ($version['path'] ?? null) : $version;
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        $paths = array_values(array_unique(array_filter($paths, static fn ($path) =>
            is_string($path) && str_starts_with($path, self::ROOT.'/')
        )));

        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }
}
