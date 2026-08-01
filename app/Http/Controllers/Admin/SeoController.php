<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SeoController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage-seo');
    }

    /**
     * نمایش صفحات SEO
     */
    public function index(): View
    {
        try {

            $seoPages = SeoPage::query()
                ->orderBy('page_type')
                ->orderBy('page_identifier')
                ->paginate(20);

            return view('admin.seo.index', compact('seoPages'));

        } catch (\Throwable $e) {

            Log::error('SEO pages load failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            abort(500);
        }
    }

    /**
     * ذخیره تنظیمات SEO
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pages' => ['required','array'],

            'pages.*.page_type' => ['required','string','max:50'],
            'pages.*.page_identifier' => ['nullable','string','max:255'],

            'pages.*.meta_title' => ['nullable','string','max:255'],
            'pages.*.meta_description' => ['nullable','string','max:500'],
            'pages.*.meta_keywords' => ['nullable','string','max:500'],

            'pages.*.og_title' => ['nullable','string','max:255'],
            'pages.*.og_description' => ['nullable','string','max:500'],

            'pages.*.og_image' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],

            'pages.*.canonical_url' => ['nullable','url','max:255'],

            'pages.*.robots' => ['nullable','string','max:100'],

            'pages.*.schema_markup' => ['nullable','array'],
        ]);

        DB::beginTransaction();

        try {

            foreach ($validated['pages'] as $index => $pageData) {

                $existing = SeoPage::query()
                    ->where('page_type', $pageData['page_type'])
                    ->where('page_identifier', $pageData['page_identifier'] ?? null)
                    ->first();

                $ogImagePath = $existing?->og_image;

                if ($request->hasFile("pages.$index.og_image")) {

                    if ($existing && $existing->og_image && Storage::disk('public')->exists($existing->og_image)) {
                        Storage::disk('public')->delete($existing->og_image);
                    }

                    $ogImagePath = $request
                        ->file("pages.$index.og_image")
                        ->store('seo/og', 'public');
                }

                SeoPage::updateOrCreate(
                    [
                        'page_type' => $pageData['page_type'],
                        'page_identifier' => $pageData['page_identifier'] ?? null,
                    ],
                    [
                        'meta_title' => $pageData['meta_title'] ?? null,
                        'meta_description' => $pageData['meta_description'] ?? null,
                        'meta_keywords' => $pageData['meta_keywords'] ?? null,

                        'og_title' => $pageData['og_title'] ?? null,
                        'og_description' => $pageData['og_description'] ?? null,
                        'og_image' => $ogImagePath,

                        'canonical_url' => $pageData['canonical_url'] ?? null,
                        'robots' => $pageData['robots'] ?? null,

                        'schema_markup' => $pageData['schema_markup'] ?? null,
                    ]
                );
            }

            DB::commit();

            return redirect()
                ->route('admin.seo.index')
                ->with('success', 'تنظیمات SEO با موفقیت ذخیره شد.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('SEO update failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'خطا در ذخیره تنظیمات SEO.');
        }
    }

    /**
     * تولید sitemap
     */
    public function generateSitemap(): RedirectResponse
    {
        try {

            $sitemap = Sitemap::create();

            // صفحه اصلی
            $sitemap->add(
                Url::create('/')
                    ->setPriority(1.0)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            );

            // دسته بندی ها
            Category::query()
                ->where('is_active', true)
                ->each(function ($category) use ($sitemap) {

                    if (!empty($category->slug)) {
                        $sitemap->add(
                            Url::create("/categories/{$category->slug}")
                                ->setPriority(0.8)
                        );
                    }
                });

            // محصولات
            Product::query()
                ->where('is_active', true)
                ->each(function ($product) use ($sitemap) {

                    if (!empty($product->slug)) {
                        $sitemap->add(
                            Url::create("/products/{$product->slug}")
                                ->setPriority(0.7)
                        );
                    }
                });

            $sitemap->writeToFile(public_path('sitemap.xml'));

            Log::info('Sitemap generated', [
                'user_id' => auth()->id(),
            ]);

            return back()->with('success', 'نقشه سایت با موفقیت ایجاد شد.');

        } catch (\Throwable $e) {

            Log::error('Sitemap generation failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'خطا در تولید sitemap.');
        }
    }
}
