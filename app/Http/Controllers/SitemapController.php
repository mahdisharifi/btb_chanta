<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap_index', now()->addHours(6), function () {
            $productsLastmod   = Product::active()->max('updated_at');
            $categoriesLastmod = app(\App\Services\CategoryCatalog::class)->flatten()->max('updated_at');

            return view('sitemap.index', [
                'sitemaps' => [
                    [
                        'loc'     => route('sitemap.static'),
                        'lastmod' => now()->toAtomString(),
                    ],
                    [
                        'loc'     => route('sitemap.products'),
                        'lastmod' => $productsLastmod
                            ? Carbon::parse($productsLastmod)->toAtomString()
                            : now()->toAtomString(),
                    ],
                    [
                        'loc'     => route('sitemap.categories'),
                        'lastmod' => $categoriesLastmod
                            ? Carbon::parse($categoriesLastmod)->toAtomString()
                            : now()->toAtomString(),
                    ],
                ],
            ])->render();
        });

        return response($xml)->header('Content-Type', 'application/xml');
    }

    public function products(): Response
    {
        $xml = Cache::remember('sitemap_products', now()->addHours(6), function () {
            $urls = [];
            Product::active()
                ->with('primaryImage')
                ->select('id', 'slug', 'updated_at')
                ->chunk(500, function ($products) use (&$urls) {
                    foreach ($products as $p) {
                        $urls[] = [
                            'loc'     => route('products.show', $p->slug),
                            'lastmod' => $p->updated_at->toAtomString(),
                            'image'   => $p->primaryImage?->full_url,
                        ];
                    }
                });
            return view('sitemap.urls', compact('urls'))->render();
        });

        return response($xml)->header('Content-Type', 'application/xml');
    }

    public function categories(): Response
    {
        $xml = Cache::remember('sitemap_categories', now()->addHours(6), function () {
            $urls = app(\App\Services\CategoryCatalog::class)->flatten()
                ->map(fn($c) => [
                    'loc'     => route('categories.show', $c->slug),
                    'lastmod' => $c->updated_at->toAtomString(),
                    'image'   => $c->image_url,
                ])->all();
            return view('sitemap.urls', compact('urls'))->render();
        });

        return response($xml)->header('Content-Type', 'application/xml');
    }

    public function static(): Response
    {
        $xml = Cache::remember('sitemap_static', now()->addHours(24), function () {
            $urls = collect([
                route('home'),
                route('products.index'),
                route('categories.index'),
                route('about'),
                route('contact'),
            ])->map(fn($url) => [
                'loc'     => $url,
                'lastmod' => now()->toAtomString(),
                'image'   => null,
            ])->all();
            return view('sitemap.urls', compact('urls'))->render();
        });

        return response($xml)->header('Content-Type', 'application/xml');
    }
}
