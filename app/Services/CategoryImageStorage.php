<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class CategoryImageStorage
{
    public static function delete(?string $path): void
    {
        if (!$path || !str_starts_with($path, 'categories/') || str_contains($path, '..') || str_contains($path, '\\')) {
            return;
        }
        try {
            if (!Storage::disk('public')->delete($path)) {
                Log::warning('Category image cleanup failed.');
            }
        } catch (\Throwable $exception) {
            Log::warning('Category image cleanup failed.', ['exception' => $exception::class]);
        }
    }
}
