<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ProductCompletionConflictResult;
use App\Models\Product;
use App\Models\ProductCompletionTask;

class ProductCompletionConflictService
{
    public function __construct(private readonly ProductCompletionSnapshotService $snapshotService)
    {
    }

    public function detect(
        ProductCompletionTask $task,
        Product $currentProduct,
        array $submittedPayload,
    ): ProductCompletionConflictResult {
        $baseSnapshot = $task->initial_snapshot_json;
        $base = $this->snapshotService->draftFromSnapshot($baseSnapshot);
        $currentSnapshot = $this->snapshotService->build($currentProduct, $task->scope_json);
        $current = $this->snapshotService->draftFromSnapshot($currentSnapshot);
        $merged = [
            'schema_version' => (int) config('product_completion.schema_version', 1),
        ];
        $conflicts = [];

        if ((int) ($baseSnapshot['category_id'] ?? 0) !== (int) $currentProduct->category_id) {
            $conflicts['category_context'] = [
                'before' => (int) ($baseSnapshot['category_id'] ?? 0),
                'current' => (int) $currentProduct->category_id,
                'reason' => 'product_category_changed',
            ];
        }

        if ($task->supplier_id !== null && (int) $task->supplier_id !== (int) $currentProduct->supplier_id) {
            $conflicts['supplier_context'] = [
                'before' => (int) $task->supplier_id,
                'current' => $currentProduct->supplier_id === null ? null : (int) $currentProduct->supplier_id,
                'reason' => 'product_supplier_changed',
            ];
        }

        $fields = array_unique(array_merge(
            array_keys($base),
            array_keys($current),
            array_keys($submittedPayload)
        ));

        foreach ($fields as $field) {
            if ($field === 'schema_version') {
                continue;
            }

            $baseValue = $base[$field] ?? null;
            $currentValue = $current[$field] ?? null;
            $submittedValue = $submittedPayload[$field] ?? null;

            if ($this->same($currentValue, $baseValue)) {
                $merged[$field] = $submittedValue;

                continue;
            }

            if ($this->same($submittedValue, $baseValue)) {
                $merged[$field] = $currentValue;

                continue;
            }

            if ($this->same($currentValue, $submittedValue)) {
                $merged[$field] = $currentValue;

                continue;
            }

            $conflicts[$field] = [
                'base' => $baseValue,
                'current' => $currentValue,
                'submitted' => $submittedValue,
                'reason' => 'concurrent_field_change',
            ];
        }

        return new ProductCompletionConflictResult(
            mergedPayload: $merged,
            conflicts: $conflicts,
            currentProductVersion: (int) $currentProduct->content_version,
        );
    }

    private function same(mixed $left, mixed $right): bool
    {
        return $this->canonicalJson($left) === $this->canonicalJson($right);
    }

    private function canonicalJson(mixed $value): string
    {
        return json_encode(
            $this->canonicalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
