<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Slider;
use App\Services\SliderImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateSliderResponsiveImages extends Command
{
    protected $signature = 'sliders:generate-responsive-images {--id= : Process only one slider ID} {--after-id= : Resume after a slider ID} {--limit= : Maximum sliders to process in this run}';

    protected $description = 'Generate responsive WebP widths for existing slider images';

    public function handle(SliderImageService $images): int
    {
        if (! function_exists('imagewebp')) {
            $this->error('افزونهٔ GD این PHP از ساخت تصویر WebP پشتیبانی نمی‌کند.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $processedImages = 0;
        $generatedVariants = 0;
        $skipped = 0;
        $failed = 0;
        $failedSliderIds = [];
        $remaining = $this->option('limit') === null ? null : max(1, (int) $this->option('limit'));
        $lastProcessedId = null;

        $query = Slider::query()->orderBy('id');
        if ($this->option('id')) {
            $query->whereKey((int) $this->option('id'));
        } elseif ($this->option('after-id')) {
            $query->where('id', '>', (int) $this->option('after-id'));
        }

        $query->chunkById(25, function ($sliders) use ($images, $disk, &$processedImages, &$generatedVariants, &$skipped, &$failed, &$failedSliderIds, &$remaining, &$lastProcessedId): bool {
            foreach ($sliders as $slider) {
                if ($remaining !== null && $remaining <= 0) {
                    return false;
                }

                $lastProcessedId = $slider->id;
                foreach ([
                    'desktop' => $slider->image_path,
                    'mobile' => $slider->mobile_image_path,
                ] as $variant => $path) {
                    if (! is_string($path) || $path === ''
                        || Str::startsWith($path, ['http://', 'https://'])
                        || ! Str::startsWith($path, 'sliders/')
                        || ! $disk->exists($path)) {
                        $skipped++;
                        continue;
                    }

                    try {
                        $created = $images->generateResponsiveVariants($path, $variant);
                        if ($created > 0) {
                            $processedImages++;
                            $generatedVariants += $created;
                        } else {
                            $skipped++;
                        }
                    } catch (\Throwable $exception) {
                        report($exception);
                        $failed++;
                        $failedSliderIds[$slider->id] = true;
                        $this->warn("پردازش تصویر اسلایدر {$slider->id} ({$variant}) ناموفق بود.");
                    }
                }

                if ($remaining !== null) {
                    $remaining--;
                }
            }

            return $remaining === null || $remaining > 0;
        });

        $this->info("تصویر پردازش‌شده: {$processedImages}؛ نسخهٔ ساخته‌شده: {$generatedVariants}؛ ردشده: {$skipped}؛ خطا: {$failed}");

        if ($remaining !== null && $remaining <= 0 && $lastProcessedId !== null) {
            $this->line("Resume with --after-id={$lastProcessedId} to continue.");
        }
        foreach (array_keys($failedSliderIds) as $failedSliderId) {
            $this->line("Retry failed slider with --id={$failedSliderId}.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
