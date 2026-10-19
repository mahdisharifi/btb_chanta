<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class SliderImageService
{
    private const DISK = 'public';

    private const WIDTHS = [320, 480, 640, 768, 1024, 1280, 1600, 1920];

    private const SRCSET_CACHE_PREFIX = 'slider:responsive-set:';

    public function processUpload(UploadedFile $file, string $variant): string
    {
        $this->assertVariant($variant);

        $directory = 'sliders/'.Str::ulid();
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory($directory);

        try {
            $path = $this->generateResponsiveFiles(
                $file->getRealPath(),
                $directory,
                $variant,
                $variant,
                true,
            );

            if (! $path) {
                throw new \RuntimeException('Unable to process the slider image.');
            }

            return $path;
        } catch (\Throwable $exception) {
            $disk->deleteDirectory($directory);
            throw $exception;
        }
    }

    /** Create missing WebP width variants beside an existing slider image without changing its path. */
    public function generateResponsiveVariants(string $sourcePath, string $variant): int
    {
        $this->assertVariant($variant);

        if (! Str::startsWith($sourcePath, 'sliders/') || str_contains($sourcePath, '..')) {
            throw new \InvalidArgumentException('Slider image paths must be inside the sliders directory.');
        }

        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($sourcePath)) {
            throw new \RuntimeException('Slider source image does not exist.');
        }

        $directory = dirname($sourcePath);
        $baseName = $this->baseNameFromPath($sourcePath);
        try {
            return $this->generateResponsiveFiles(
                $disk->path($sourcePath),
                $directory,
                $baseName,
                $variant,
                false,
            );
        } finally {
            Cache::forget(self::SRCSET_CACHE_PREFIX.md5($sourcePath));
        }
    }

    /** Return responsive sources and a size-limited fallback from existing, exactly named variants. */
    public function responsiveSet(?string $path): ?array
    {
        if (! is_string($path) || ! Str::startsWith($path, 'sliders/') || str_contains($path, '..')) {
            return null;
        }

        $sources = Cache::remember(self::SRCSET_CACHE_PREFIX.md5($path), now()->addHours(6), function () use ($path): array {
            $directory = dirname($path);
            $baseName = $this->baseNameFromPath($path);
            $disk = Storage::disk(self::DISK);
            $sources = [];
            $pattern = '/^'.preg_quote($baseName, '/').'-(\d+)\.webp$/i';

            foreach ($disk->files($directory) as $candidate) {
                $candidateName = basename($candidate);
                if (preg_match($pattern, $candidateName, $matches) === 1) {
                    $width = (int) $matches[1];
                    $sources[$width] = $candidate;
                }
            }

            ksort($sources, SORT_NUMERIC);

            return $sources;
        });

        $srcset = [];
        foreach ($sources as $width => $sourcePath) {
            $srcset[] = asset('storage/'.$sourcePath).' '.$width.'w';
        }

        $largestPath = $sources === [] ? $path : end($sources);

        return [
            'srcset' => $srcset === [] ? null : implode(', ', $srcset),
            'fallback' => asset('storage/'.$largestPath),
        ];
    }

    public function delete(?string $path): void
    {
        if (! is_string($path) || ! Str::startsWith($path, 'sliders/') || str_contains($path, '..')) {
            return;
        }

        $disk = Storage::disk(self::DISK);
        $directory = dirname($path);
        $baseName = $this->baseNameFromPath($path);
        $files = [$path];

        foreach ($disk->files($directory) as $candidate) {
            $candidateName = pathinfo($candidate, PATHINFO_FILENAME);
            if (preg_match('/^'.preg_quote($baseName, '/').'-(\d+)$/', $candidateName) === 1
                && strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) === 'webp') {
                $files[] = $candidate;
            }
        }

        $disk->delete(array_unique($files));
        Cache::forget(self::SRCSET_CACHE_PREFIX.md5($path));
    }

    private function generateResponsiveFiles(
        string $sourceFile,
        string $directory,
        string $baseName,
        string $variant,
        bool $includeBase,
    ): int|string|null {
        $disk = Storage::disk(self::DISK);
        $manager = ImageManager::gd();
        $sourceSize = @getimagesize($sourceFile);
        if (! is_array($sourceSize) || empty($sourceSize[0]) || empty($sourceSize[1])) {
            throw new \RuntimeException('ابعاد تصویر قابل خواندن نیست.');
        }

        $sourceWidth = (int) $sourceSize[0];
        $maxWidth = $variant === 'mobile' ? 1000 : 1920;
        $widths = array_values(array_unique(array_filter(
            [...self::WIDTHS, $maxWidth, min($sourceWidth, $maxWidth)],
            fn (int $width): bool => $width <= min($sourceWidth, $maxWidth),
        )));

        if ($widths === []) {
            $widths = [min($sourceWidth, $maxWidth)];
        }

        $widths = array_values(array_filter($widths, function (int $width) use ($disk, $directory, $baseName): bool {
            return ! $disk->exists("{$directory}/{$baseName}-{$width}.webp");
        }));

        if ($widths === []) {
            return $includeBase ? null : 0;
        }

        $this->assertSafeProcessingMemory((int) $sourceSize[0], (int) $sourceSize[1]);

        $generated = 0;
        $largestPath = null;

        foreach ($widths as $width) {
            $variantImage = $manager->read($sourceFile)->scaleDown(width: $width);
            $actualWidth = $variantImage->width();
            $variantPath = "{$directory}/{$baseName}-{$actualWidth}.webp";
            if ($disk->exists($variantPath)) {
                $largestPath = $variantPath;
                continue;
            }

            $temporaryRelativePath = "{$directory}/.slider-".Str::random(12).'.tmp';
            $temporaryPath = $disk->path($temporaryRelativePath);

            try {
                $contents = $variantImage->toWebp(quality: 78)->toString();

                if (! $disk->put($temporaryRelativePath, $contents)) {
                    throw new \RuntimeException('Unable to write a temporary slider image variant.');
                }

                if (! rename($temporaryPath, $disk->path($variantPath))) {
                    throw new \RuntimeException('Unable to finalize a slider image variant.');
                }

                $generated++;
                $largestPath = $variantPath;
            } catch (\Throwable $exception) {
                if (is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
                throw $exception;
            }
        }

        if ($includeBase && $largestPath) {
            return $largestPath;
        }

        return $generated;
    }

    private function assertVariant(string $variant): void
    {
        if (! in_array($variant, ['desktop', 'mobile'], true)) {
            throw new \InvalidArgumentException('Invalid slider image variant.');
        }
    }

    private function baseNameFromPath(string $path): string
    {
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'webp' && preg_match('/^(desktop|mobile)-\d+$/', $baseName) === 1) {
            return preg_replace('/-\d+$/', '', $baseName) ?: $baseName;
        }

        return $baseName;
    }

    private function assertSafeProcessingMemory(int $width, int $height): void
    {
        $limit = trim((string) ini_get('memory_limit'));
        $unit = strtolower(substr($limit, -1));
        $value = (float) $limit;
        $multiplier = match ($unit) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };
        $configuredBudgetMb = max(32, (int) config('sliders.image_memory_limit_mb', 128));
        $configuredBudgetBytes = $configuredBudgetMb * 1024 * 1024;
        $phpLimitBytes = $limit === '' || $limit === '-1'
            ? $configuredBudgetBytes
            : (int) ($value * $multiplier);
        $limitBytes = min($phpLimitBytes, $configuredBudgetBytes);
        $reserveBytes = max(16 * 1024 * 1024, (int) ($limitBytes * 0.2));
        $availableBytes = max(0, $limitBytes - memory_get_usage(true) - $reserveBytes);
        // Reserve for the decoded source plus GD resize/output buffers.
        $estimatedPeakBytes = $width * $height * 8;

        if ($estimatedPeakBytes > $availableBytes) {
            throw new \RuntimeException('حافظهٔ فعلی PHP برای پردازش این تصویر کافی نیست. تصویر را با ابعاد کوچک‌تر بارگذاری کنید.');
        }
    }
}
