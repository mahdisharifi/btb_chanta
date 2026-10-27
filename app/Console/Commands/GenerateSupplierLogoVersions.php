<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\SupplierLogoImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateSupplierLogoVersions extends Command
{
    protected $signature = 'suppliers:generate-logo-versions {--force : Regenerate existing logo versions}';

    protected $description = 'Generate web and catalog PDF variants for supplier logos';

    public function handle(SupplierLogoImageService $images): int
    {
        $disk = Storage::disk('public');
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        Supplier::query()
            ->whereNotNull('logo_path')
            ->orderBy('id')
            ->chunkById(100, function ($suppliers) use ($images, $disk, &$processed, &$skipped, &$failed): void {
                foreach ($suppliers as $supplier) {
                    $path = (string) $supplier->logo_path;

                    if (Str::startsWith($path, ['http://', 'https://']) || ! $disk->exists($path)) {
                        $skipped++;
                        continue;
                    }

                    if (! $this->option('force')
                        && filled(data_get($supplier->logo_versions, 'thumbnail.path'))
                        && filled(data_get($supplier->logo_versions, 'small.path'))
                        && filled(data_get($supplier->logo_versions, 'medium.path'))
                        && filled(data_get($supplier->logo_versions, 'pdf.path'))) {
                        $skipped++;
                        continue;
                    }

                    $newVersions = null;
                    try {
                        $newVersions = $images->generateVersions($disk->path($path));
                        $oldVersions = $supplier->logo_versions;
                        $supplier->forceFill(['logo_versions' => $newVersions])->saveQuietly();
                        $images->delete(null, $oldVersions);
                        $processed++;
                    } catch (\Throwable $exception) {
                        if ($newVersions) {
                            $images->delete(null, $newVersions);
                        }
                        report($exception);
                        $failed++;
                    }
                }
            });

        $this->info("Logo variants generated: {$processed}; skipped: {$skipped}; failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
