<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductSlugRedirect;
use App\Traits\GeneratesPersianSlug;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegenerateProductPersianSlugs extends Command
{
    use GeneratesPersianSlug;

    protected $signature = 'products:regenerate-persian-slugs
                            {--dry-run : فقط نمایش تغییرات بدون ذخیره}
                            {--force : اجرای بدون سوال تایید}';

    protected $description = 'Regenerate all product slugs from product titles using Persian-friendly slugs';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('All product slugs will be regenerated. Continue?')) {
                $this->warn('Cancelled.');

                return self::SUCCESS;
            }
        }

        $changed = 0;
        $skipped = 0;

        Product::query()
            ->select(['id', 'title', 'slug'])
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($dryRun, &$changed, &$skipped) {
                foreach ($products as $product) {
                    $newSlug = $this->makeUniquePersianSlug($product->title, $product->id);

                    if ($product->slug === $newSlug) {
                        $skipped++;
                        $this->line("SKIP #{$product->id}: {$product->slug}");
                        continue;
                    }

                    $changed++;

                    $this->info("CHANGE #{$product->id}");
                    $this->line("TITLE: {$product->title}");
                    $this->line("OLD:   {$product->slug}");
                    $this->line("NEW:   {$newSlug}");
                    $this->newLine();

                    if (! $dryRun) {
                        // ثبت 301 redirect قبل از تغییر slug تا لینک قدیمی ایندکس‌شده در گوگل از دست نرود
                        ProductSlugRedirect::updateOrCreate(
                            ['old_slug' => $product->slug],
                            ['product_id' => $product->id]
                        );

                        DB::table('products')
                            ->where('id', $product->id)
                            ->update([
                                'slug' => $newSlug,
                                'updated_at' => now(),
                            ]);
                    }
                }
            });

        $this->newLine();

        if ($dryRun) {
            $this->warn('Dry-run completed. No database changes were saved.');
        } else {
            $this->info('Product slugs regenerated successfully.');
        }

        $this->line("Changed: {$changed}");
        $this->line("Skipped: {$skipped}");

        return self::SUCCESS;
    }

    private function makeUniquePersianSlug(string $title, int $ignoreProductId): string
    {
        $baseSlug = $this->makePersianSlug($title);

        // Keep enough room for numeric suffixes and stay below VARCHAR(255).
        $baseSlug = mb_substr($baseSlug, 0, 220);

        $slug = $baseSlug;
        $counter = 2;

        while (
        Product::query()
            ->where('id', '!=', $ignoreProductId)
            ->where('slug', $slug)
            ->exists()
        ) {
            $suffix = '-' . $counter;
            $slug = mb_substr($baseSlug, 0, 220 - mb_strlen($suffix)) . $suffix;
            $counter++;
        }

        return $slug;
    }
}
