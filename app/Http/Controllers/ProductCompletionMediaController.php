<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionTask;
use App\Models\ProductGallery;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCompletionMediaController extends Controller
{
    public function gallery(ProductCompletionTask $task, ProductGallery $gallery): StreamedResponse
    {
        if (! Gate::allows('viewTask', $task)) {
            Gate::authorize('previewAvailableTask', $task);
        }
        abort_unless($gallery->product_id === $task->product_id && $gallery->file_type === 'image', 404);
        $disk = Storage::disk(ProductGallery::STORAGE_DISK);
        abort_unless($disk->exists($gallery->file_path), 404);

        return $disk->response($gallery->file_path, basename($gallery->file_path), [
            'Content-Type' => $disk->mimeType($gallery->file_path),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

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
