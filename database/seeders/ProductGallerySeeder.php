<?php
// database/seeders/ProductGallerySeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductGallery;
use Illuminate\Support\Facades\Storage;

class ProductGallerySeeder extends Seeder
{
    public function run(): void
    {
        // پاک کردن رکوردهای قبلی
        ProductGallery::truncate();

        // ایجاد دایرکتوری‌ها
        Storage::disk('public')->makeDirectory('products/images');
        Storage::disk('public')->makeDirectory('products/videos');

        $colorPalettes = [
            ['bg' => '#EEF2FF', 'accent' => '#4F46E5', 'label' => 'الکترونیک'],
            ['bg' => '#FEF3C7', 'accent' => '#D97706', 'label' => 'خانگی'],
            ['bg' => '#ECFDF5', 'accent' => '#059669', 'label' => 'بهداشتی'],
            ['bg' => '#FFF1F2', 'accent' => '#E11D48', 'label' => 'غذایی'],
            ['bg' => '#F5F3FF', 'accent' => '#7C3AED', 'label' => 'عمومی'],
        ];

        $products = Product::where('is_active', true)->get();

        $this->command->info("🖼️  شروع ایجاد گالری برای {$products->count()} محصول...");

        foreach ($products as $product) {
            $palette = $colorPalettes[$product->category_id % count($colorPalettes)];
            $variants = [
                ['suffix' => 'primary', 'label' => 'تصویر اصلی', 'is_primary' => true, 'type' => 'image'],
                ['suffix' => 'angle2', 'label' => 'نمای جانبی', 'is_primary' => false, 'type' => 'image'],
                ['suffix' => 'detail', 'label' => 'جزئیات', 'is_primary' => false, 'type' => 'image'],
                ['suffix' => 'video', 'label' => 'ویدئو', 'is_primary' => false, 'type' => 'video'],
            ];

            foreach ($variants as $sortOrder => $variant) {
                if ($variant['type'] === 'image') {
                    $svg = $this->generateSvg($product, $variant, $palette);
                    $fileName = "product-{$product->id}-{$variant['suffix']}.svg";
                    $filePath = "products/images/{$fileName}";
                    Storage::disk('public')->put($filePath, $svg);

                    $altText = match ($variant['suffix']) {
                        'primary' => "تصویر اصلی {$product->title} - خرید عمده از " . config('app.name'),
                        'angle2'  => "نمای جانبی {$product->title} - مشخصات و قیمت",
                        'detail'  => "جزئیات محصول {$product->title} - ویژگی‌ها و مشخصات فنی",
                        default   => $product->title,
                    };
                } else {
                    // ویدئو placeholder
                    $fileName = "product-{$product->id}-demo.txt";
                    $filePath = "products/videos/{$fileName}";
                    Storage::disk('public')->put($filePath,
                        "Video placeholder for: {$product->title}\nUpload real .mp4 file to replace."
                    );
                    $altText = "ویدئو معرفی {$product->title} - بررسی و آنباکسینگ";
                }

                ProductGallery::create([
                    'product_id' => $product->id,
                    'file_path'  => $filePath,
                    'file_type'  => $variant['type'],
                    'alt_text'   => $altText,
                    'sort_order' => $sortOrder,
                    'is_primary' => $variant['is_primary'],
                ]);
            }

            $this->command->info("  ✅ [{$product->id}] {$product->title} — ۳ تصویر + ۱ ویدئو");
        }

        $this->command->newLine();
        $this->command->info("🎉 تکمیل! مجموع " . ProductGallery::count() . " فایل ایجاد شد.");
    }

    private function generateSvg(Product $product, array $variant, array $palette): string
    {
        $title = htmlspecialchars(mb_substr($product->title, 0, 28), ENT_XML1, 'UTF-8');
        $sku = htmlspecialchars($product->sku ?? 'N/A', ENT_XML1, 'UTF-8');
        $variantLabel = htmlspecialchars($variant['label'], ENT_XML1, 'UTF-8');
        $catLabel = htmlspecialchars($palette['label'], ENT_XML1, 'UTF-8');
        $bg = $palette['bg'];
        $accent = $palette['accent'];
        $id = $product->id;
        $uid = "{$id}-{$variant['suffix']}";

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800">
  <defs>
    <linearGradient id="g-{$uid}" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$bg}" />
      <stop offset="100%" style="stop-color:#ffffff;stop-opacity:0.7" />
    </linearGradient>
  </defs>
  <rect width="800" height="800" fill="url(#g-{$uid})" rx="16"/>
  <rect width="800" height="800" fill="none" stroke="{$accent}" stroke-opacity="0.12" stroke-width="2" rx="16"/>
  <circle cx="400" cy="250" r="65" fill="{$accent}" opacity="0.1"/>
  <text x="400" y="265" text-anchor="middle" font-size="52">📦</text>
  <text x="400" y="420" text-anchor="middle" fill="{$accent}" font-size="26" font-weight="bold" font-family="Tahoma,Arial,sans-serif" direction="rtl">{$title}</text>
  <text x="400" y="470" text-anchor="middle" fill="{$accent}" font-size="16" opacity="0.55" font-family="Consolas,monospace">SKU: {$sku}</text>
  <rect x="330" y="520" width="140" height="32" rx="16" fill="{$accent}" opacity="0.12"/>
  <text x="400" y="542" text-anchor="middle" fill="{$accent}" font-size="15" font-weight="bold" font-family="Tahoma,Arial,sans-serif">{$variantLabel}</text>
  <rect x="350" y="580" width="100" height="24" rx="12" fill="{$accent}" opacity="0.07"/>
  <text x="400" y="597" text-anchor="middle" fill="{$accent}" font-size="12" opacity="0.45" font-family="Tahoma,Arial,sans-serif">{$catLabel}</text>
  <rect x="20" y="20" width="48" height="24" rx="12" fill="{$accent}" opacity="0.1"/>
  <text x="44" y="37" text-anchor="middle" fill="{$accent}" font-size="13" font-weight="bold" font-family="Consolas">#{$id}</text>
</svg>
SVG;
    }
}
