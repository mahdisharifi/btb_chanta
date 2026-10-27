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
    public function __construct(private readonly ProductCompletionInputSanitizer $sanitizer)
    {
    }

    /**
     * Validate attribute selections without publishing proposed values.
     * Proposed values remain inside the draft/submission payload until approval.
     *
     * @param array<int, array{attribute_id: mixed, value_ids?: array<int, mixed>, proposed_values?: array<int, mixed>}> $selections
     * @return array<int, array{attribute_id: int, value_ids: array<int, int>, proposed_values: array<int, string>}>
     */
    public function validate(ProductCompletionTask $task, array $selections, bool $forSubmission = false): array
    {
        $product = $task->product()->select(['id', 'category_id'])->firstOrFail();
        $attributeIds = collect($selections)
            ->pluck('attribute_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $categoryAttributes = DB::table('attribute_category')
            ->where('category_id', $product->category_id)
            ->select(['attribute_id', 'is_required'])
            ->get();
        $allowedAttributeIds = $categoryAttributes
            ->pluck('attribute_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $requiredAttributeIds = $categoryAttributes
            ->filter(static fn (object $attribute): bool => (bool) $attribute->is_required)
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
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $values = AttributeValue::query()
            ->select(['id', 'attribute_id'])
            ->whereIn('id', $valueIds)
            ->get()
            ->keyBy('id');

        $maxLength = (int) config('product_completion.validation.attribute_proposed_value_max', 255);
        $maxProposedValues = (int) config('product_completion.validation.attribute_proposed_values_max', 10);
        $errors = [];
        $canonical = [];

        foreach (array_values($selections) as $index => $selection) {
            $attributeId = (int) ($selection['attribute_id'] ?? 0);
            $attribute = $attributes->get($attributeId);

            if ($attribute === null || ! in_array($attributeId, $allowedAttributeIds, true)) {
                $errors["attributes.{$index}.attribute_id"] = 'این ویژگی به دسته‌بندی محصول مرتبط نیست.';

                continue;
            }

            $selectedValueIds = collect($selection['value_ids'] ?? [])
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->sort()
                ->values()
                ->all();

            foreach ($selectedValueIds as $valueId) {
                $value = $values->get($valueId);

                if ($value === null || (int) $value->attribute_id !== $attributeId) {
                    $errors["attributes.{$index}.value_ids"] = 'حداقل یکی از مقادیر انتخاب‌شده متعلق به این ویژگی نیست.';
                    continue 2;
                }
            }

            $proposedValues = [];
            foreach (array_slice($selection['proposed_values'] ?? [], 0, $maxProposedValues) as $proposedValue) {
                $cleanValue = $this->sanitizer->plainText(
                    is_string($proposedValue) ? $proposedValue : null,
                    $maxLength,
                );

                if ($cleanValue === null) {
                    $errors["attributes.{$index}.proposed_values"] = 'مقدار پیشنهادی ویژگی معتبر نیست.';
                    continue;
                }

                $proposedValues[] = $cleanValue;
            }

            if (isset($errors["attributes.{$index}.proposed_values"])) {
                continue;
            }

            $proposedValues = array_values(array_unique($proposedValues));
            $existingProposedValues = AttributeValue::query()
                ->where('attribute_id', $attributeId)
                ->whereIn('value', $proposedValues)
                ->pluck('id', 'value');

            foreach ($proposedValues as $key => $proposedValue) {
                if ($existingProposedValues->has($proposedValue)) {
                    $selectedValueIds[] = (int) $existingProposedValues->get($proposedValue);
                    unset($proposedValues[$key]);
                }
            }

            $selectedValueIds = array_values(array_unique($selectedValueIds));
            $proposedValues = array_values($proposedValues);

            if (! $attribute->is_multiple && count($selectedValueIds) + count($proposedValues) > 1) {
                $errors["attributes.{$index}.value_ids"] = 'برای این ویژگی فقط یک مقدار قابل انتخاب یا پیشنهاد است.';

                continue;
            }

            $canonical[] = [
                'attribute_id' => $attributeId,
                'value_ids' => $selectedValueIds,
                'proposed_values' => $proposedValues,
            ];
        }

        if ($forSubmission) {
            $completedAttributeIds = collect($canonical)
                ->filter(static fn (array $selection): bool => $selection['value_ids'] !== [] || $selection['proposed_values'] !== [])
                ->pluck('attribute_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $missingRequiredAttributeIds = array_values(array_diff($requiredAttributeIds, $completedAttributeIds));

            if ($missingRequiredAttributeIds !== []) {
                $requiredNames = Attribute::query()
                    ->whereIn('id', $missingRequiredAttributeIds)
                    ->orderBy('name')
                    ->pluck('name')
                    ->implode('، ');
                $errors['attributes.required'] = 'تکمیل ویژگی‌های اجباری زیر الزامی است: '.$requiredNames;
            }
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
