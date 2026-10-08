<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProductCompletionMedia;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCompletionMediaController extends Controller
{
    public function show(ProductCompletionMedia $media): StreamedResponse
    {
        Gate::authorize('view', $media);

        if ($media->temporary_path === null
            || ! Storage::disk($media->disk)->exists($media->temporary_path)) {
            abort(404);
        }

        return Storage::disk($media->disk)->response(
            $media->temporary_path,
            $media->safe_name,
            [
                'Content-Type' => $media->mime_type,
                'Content-Disposition' => 'inline; filename="'.$media->safe_name.'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]
        );
    }
}
