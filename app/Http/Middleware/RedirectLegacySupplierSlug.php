<?php

namespace App\Http\Middleware;

use App\Models\Supplier;
use App\Models\SupplierSlugRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLegacySupplierSlug
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('slug');

        if ($slug === '' || Supplier::where('slug', $slug)->exists()) {
            return $next($request);
        }

        $redirect = SupplierSlugRedirect::with(['supplier' => fn ($query) => $query
            ->where('is_active', true)->select('id', 'slug')])
            ->where('old_slug', $slug)
            ->first();

        if (! $redirect?->supplier || blank($redirect->supplier->slug) || $redirect->supplier->slug === $slug) {
            return $next($request);
        }

        return redirect()->route('suppliers.show', $redirect->supplier->slug, 301);
    }
}
