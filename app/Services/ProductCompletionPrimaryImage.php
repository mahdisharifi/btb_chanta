<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductCompletionTask;

class ProductCompletionPrimaryImage
{
    public function apply(ProductCompletionTask $task, array $gallery): array
    {
        if (! is_array($gallery['items'] ?? null)) {
            return $gallery;
        }

        $originalPrimaryId = null;
        foreach (data_get($task->initial_snapshot_json, 'gallery.items', []) as $item) {
            if (! empty($item['is_primary'])) {
                $originalPrimaryId = $item['product_gallery_id'] ?? null;
                break;
            }
        }

        $first = null;
        $firstNewId = null;
        $existing = [];
        $primary = null;
        foreach ($gallery['items'] as $index => $item) {
            if (! is_array($item) || ($item['action'] ?? '') === 'remove') {
                continue;
            }
            if (($item['action'] ?? '') === 'keep') {
                $existing[(int) ($item['product_gallery_id'] ?? 0)] = $index;
            } elseif (($item['action'] ?? '') === 'add') {
                $mediaId = (string) ($item['media_public_id'] ?? '');
                if ($firstNewId === null || strcmp($mediaId, $firstNewId) < 0) {
                    $first = $index;
                    $firstNewId = $mediaId;
                }
            }
            if ($originalPrimaryId !== null && ($item['action'] ?? '') === 'keep'
                && (int) ($item['product_gallery_id'] ?? 0) === (int) $originalPrimaryId) {
                $primary = $index;
            }
        }
        if ($primary === null) {
            foreach (data_get($task->initial_snapshot_json, 'gallery.items', []) as $original) {
                $id = (int) ($original['product_gallery_id'] ?? 0);
                if (array_key_exists($id, $existing)) {
                    $primary = $existing[$id];
                    break;
                }
            }
        }
        // Staged media use chronological ULIDs; client array order cannot choose the primary image.
        $primary ??= $first;

        foreach ($gallery['items'] as $index => &$item) {
            if (is_array($item)) {
                $item['is_primary'] = $primary !== null && $index === $primary;
            }
        }
        unset($item);

        return $gallery;
    }
}
