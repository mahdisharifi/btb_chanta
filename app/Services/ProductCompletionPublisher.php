<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;

class ProductCompletionPublisher
{
    public function __construct(private readonly ProductCompletionDraftValidator $validator)
    {
    }

    /**
     * Apply only allowlisted content fields. The caller must hold a Product row lock.
     */
    public function apply(
        ProductCompletionTask $task,
        Product $product,
        array $payload,
        ?User $actor = null,
        ?array $approvedFields = null,
        ?ProductCompletionSubmission $submission = null,
    ): Product {
        $content = $submission !== null && data_get($submission->approval_selection_json, 'schema_version') === 2
            ? app(ProductCompletionReviewService::class)->validatePublication($task, $submission, $payload, $actor)
            : $this->validator->validate($task, $payload, $actor, true)->toArray();
        if ($approvedFields !== null && $approvedFields !== []) {
            $content = $this->filterForApproval($content, array_values(array_map('strval', $approvedFields)));
        }
        $updates = [];

        foreach (['title', 'short_description', 'full_description'] as $field) {
            if ($submission !== null
                && data_get($submission->approval_selection_json, 'schema_version') === 2
                && ($submission->approval_selection_json['decisions']['text:'.$field] ?? null) === 'reject') {
                continue;
            }
            if ($this->scopeAllows($task, $field) && array_key_exists($field, $content)) {
                $updates[$field] = $content[$field];
            }
        }

        $attributesTouched = $this->scopeAllows($task, 'attributes')
            && array_key_exists('attributes', $content);

        if ($attributesTouched) {
            $this->syncAttributes($product, $content['attributes']);
        }

        $product->fill($updates);

        if ($product->isDirty() || $attributesTouched) {
            $product->forceFill([
                'content_version' => (int) $product->content_version + 1,
                'content_updated_at' => now(),
            ])->save();
        }

        return $product->refresh();
    }

    public function filterForApproval(array $content, array $approvedFields): array
    {
        $approved = array_fill_keys($approvedFields, true);
        foreach (['title', 'short_description', 'full_description'] as $field) {
            if (! isset($approved[$field])) {
                unset($content[$field]);
            }
        }

        foreach (['attributes', 'gallery'] as $collection) {
            if (! array_key_exists($collection, $content) || isset($approved[$collection])) {
                continue;
            }
            $content[$collection] = collect($content[$collection])
                ->values()
                ->filter(fn ($item, $index): bool => isset($approved[$collection.'.'.$index]))
                ->values()
                ->all();
            if ($content[$collection] === []) {
                unset($content[$collection]);
            }
        }

        return $content;
    }

    private function syncAttributes(Product $product, array $attributes): void
    {
        $valueIds = [];

        foreach ($attributes as $attribute) {
            $attributeId = (int) $attribute['attribute_id'];
            $valueIds = [...$valueIds, ...array_map('intval', $attribute['value_ids'] ?? [])];

            foreach ($attribute['proposed_values'] ?? [] as $proposedValue) {
                $value = AttributeValue::query()->firstOrCreate([
                    'attribute_id' => $attributeId,
                    'value' => $proposedValue,
                ]);
                $valueIds[] = (int) $value->getKey();
            }
        }

        $valueIds = array_values(array_unique($valueIds));
        $valueTexts = AttributeValue::query()
            ->whereIn('id', $valueIds)
            ->pluck('value', 'id');
        $syncData = [];

        foreach ($valueIds as $valueId) {
            $syncData[$valueId] = ['value' => $valueTexts[$valueId] ?? null];
        }

        $product->attributeValues()->sync($syncData);
    }

    private function scopeAllows(ProductCompletionTask $task, string $key): bool
    {
        $scope = data_get($task->scope_json, $key, false);

        return $scope === true || is_array($scope);
    }
}
