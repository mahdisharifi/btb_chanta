<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductCompletionTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductCompletionAttributeValidator
{
    public function validate(ProductCompletionTask $task, array $selections): array
    {
        if ($selections === []) {
            return [];
        }

        $product = $task->product()->select(['id', 'category_id'])->firstOrFail();
        $attributeIds = collect($selections)
            ->pluck('attribute_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $allowedAttributeIds = DB::table('attribute_category')
            ->where('category_id', $product->category_id)
            ->whereIn('attribute_id', $attributeIds)
            ->pluck('attribute_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $attributes = Attribute::query()
            ->select(['id', 'is_multiple'])
            ->whereIn('id', $attributeIds)
            ->get()
            ->keyBy('id');

        $valueIds = collect($selections)
            ->flatMap(static fn (array $selection): array => $selection['value_ids'] ?? [])
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $values = AttributeValue::query()
            ->select(['id', 'attribute_id'])
            ->whereIn('id', $valueIds)
            ->get()
            ->keyBy('id');

        $errors = [];
        $canonical = [];

        foreach (array_values($selections) as $index => $selection) {
            $attributeId = (int) ($selection['attribute_id'] ?? 0);
            $selectedValueIds = collect($selection['value_ids'] ?? [])
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->sort()
                ->values()
                ->all();
            $attribute = $attributes->get($attributeId);

            if ($attribute === null || ! in_array($attributeId, $allowedAttributeIds, true)) {
                $errors["attributes.{$index}.attribute_id"] = 'این ویژگی به دسته‌بندی محصول مرتبط نیست.';

                continue;
            }

            if (! $attribute->is_multiple && count($selectedValueIds) > 1) {
                $errors["attributes.{$index}.value_ids"] = 'برای این ویژگی فقط یک مقدار قابل انتخاب است.';

                continue;
            }

            foreach ($selectedValueIds as $valueId) {
                $value = $values->get($valueId);

                if ($value === null || (int) $value->attribute_id !== $attributeId) {
                    $errors["attributes.{$index}.value_ids"] = 'حداقل یکی از مقادیر انتخاب‌شده متعلق به این ویژگی نیست.';
                    break;
                }
            }

            $canonical[] = [
                'attribute_id' => $attributeId,
                'value_ids' => $selectedValueIds,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        usort(
            $canonical,
            static fn (array $left, array $right): int => $left['attribute_id'] <=> $right['attribute_id']
        );

        return $canonical;
    }
}
