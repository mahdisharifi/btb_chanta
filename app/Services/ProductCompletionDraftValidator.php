<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ProductCompletionDraftData;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductCompletionDraftValidator
{
    public function __construct(
        private readonly ProductCompletionScopeGuard $scopeGuard,
        private readonly ProductCompletionInputSanitizer $sanitizer,
        private readonly ProductCompletionAttributeValidator $attributeValidator,
    ) {
    }

    public function validate(
        ProductCompletionTask $task,
        array $payload,
        ?User $actor = null,
        bool $forSubmission = false,
    ): ProductCompletionDraftData {
        $scoped = $this->scopeGuard->filter($task, $payload, $actor);
        $validated = Validator::make(
            $scoped,
            $this->rules($task, $forSubmission),
            $this->messages()
        )->validate();


        return ProductCompletionDraftData::fromValidated(
            $this->canonicalize($task, $validated, $forSubmission)
        );
    }

    private function rules(ProductCompletionTask $task, bool $forSubmission): array
    {
        $validation = config('product_completion.validation');

        return [
            'schema_version' => [
                'required',
                'integer',
                Rule::in([(int) config('product_completion.schema_version', 1)]),
            ],
            'title' => [
                Rule::requiredIf($forSubmission && $this->scopeAllows($task, 'title')),
                'nullable',
                'string',
                'max:'.(int) $validation['title_max'],
                $this->validTitleCharacters(),
            ],
            'short_description' => [
                Rule::requiredIf($forSubmission && $this->scopeAllows($task, 'short_description')),
                'nullable',
                'string',
                'max:'.(int) $validation['short_description_max'],
                $this->withoutControlCharacters(),
            ],
            'full_description' => [
                Rule::requiredIf($forSubmission && $this->scopeAllows($task, 'full_description')),
                'nullable',
                'string',
                'max:'.(int) $validation['full_description_max'],
                $this->withoutControlCharacters(),
            ],
            'attributes' => [
                Rule::when($forSubmission && $this->scopeAllows($task, 'attributes'), ['present']),
                'array',
                'max:'.(int) $validation['attributes_max'],
            ],
            'attributes.*' => ['required', 'array:attribute_id,value_ids,proposed_values'],
            'attributes.*.attribute_id' => ['required', 'integer', 'min:1', 'distinct'],
            'attributes.*.value_ids' => [
                'present',
                'array',
                'max:'.(int) $validation['attribute_values_max'],
            ],
            'attributes.*.value_ids.*' => ['integer', 'min:1', 'distinct'],
            'attributes.*.proposed_values' => [
                'nullable',
                'array',
                'max:'.(int) ($validation['attribute_proposed_values_max'] ?? 10),
            ],
            'attributes.*.proposed_values.*' => [
                'required',
                'string',
                'max:'.(int) ($validation['attribute_proposed_value_max'] ?? 255),
                $this->withoutControlCharacters(),
            ],
            // Detailed media manifest validation belongs to ProductCompletionMediaService.
            'gallery' => ['sometimes', 'array'],
        ];
    }

    private function canonicalize(ProductCompletionTask $task, array $validated, bool $forSubmission): array
    {
        $validation = config('product_completion.validation');
        $canonical = [
            'schema_version' => (int) config('product_completion.schema_version', 1),
        ];

        if (array_key_exists('title', $validated)) {
            $canonical['title'] = $this->sanitizer->plainText(
                $validated['title'],
                (int) $validation['title_max']
            );
        }

        if (array_key_exists('short_description', $validated)) {
            $canonical['short_description'] = $this->sanitizer->plainText(
                $validated['short_description'],
                (int) $validation['short_description_max'],
                true
            );
        }

        if (array_key_exists('full_description', $validated)) {
            $canonical['full_description'] = $this->sanitizer->plainText(
                $validated['full_description'],
                (int) $validation['full_description_max'],
                true
            );
        }

        if (array_key_exists('attributes', $validated)) {
            $canonical['attributes'] = $this->attributeValidator->validate(
                $task,
                $validated['attributes'],
                $forSubmission,
            );
        }

        if (array_key_exists('gallery', $validated)) {
            $canonical['gallery'] = $validated['gallery'];
        }

        return $canonical;
    }

    private function validTitleCharacters(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (preg_match('~^[\p{L}\p{N}\s\x{200C}_ـ،,:؛.!؟()\[\]/+%&\'"#-]+$~u', (string) $value) !== 1) {
                $fail('عنوان شامل کاراکتر غیرمجاز است.');
            }
        };
    }

    private function withoutControlCharacters(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', (string) $value) === 1) {
                $fail('این فیلد شامل کاراکتر کنترلی غیرمجاز است.');
            }
        };
    }

    private function scopeAllows(ProductCompletionTask $task, string $key): bool
    {
        $scope = data_get($task->scope_json, $key, false);

        return $scope === true || is_array($scope);
    }

    private function messages(): array
    {
        return [
            'title.required' => 'عنوان محصول الزامی است.',
            'title.max' => 'عنوان محصول نباید بیشتر از :max کاراکتر باشد.',
            'short_description.required' => 'توضیحات کوتاه الزامی است.',
            'short_description.max' => 'توضیحات کوتاه نباید بیشتر از :max کاراکتر باشد.',
            'full_description.required' => 'توضیحات کامل الزامی است.',
            'full_description.max' => 'توضیحات کامل نباید بیشتر از :max کاراکتر باشد.',
            'attributes.required' => 'ویژگی‌های محصول الزامی است.',
            'attributes.array' => 'ساختار ویژگی‌های محصول نامعتبر است.',
            'attributes.*.attribute_id.required' => 'شناسه ویژگی الزامی است.',
            'attributes.*.attribute_id.integer' => 'شناسه ویژگی نامعتبر است.',
            'attributes.*.attribute_id.distinct' => 'یک ویژگی نباید دوبار ارسال شود.',
            'attributes.*.value_ids.array' => 'مقادیر ویژگی باید به‌صورت آرایه ارسال شوند.',
            'attributes.*.value_ids.*.integer' => 'شناسه مقدار ویژگی نامعتبر است.',
            'attributes.*.proposed_values.array' => 'مقادیر پیشنهادی ویژگی باید به‌صورت فهرست ارسال شوند.',
            'attributes.*.proposed_values.*.required' => 'مقدار پیشنهادی ویژگی نمی‌تواند خالی باشد.',
            'attributes.*.proposed_values.*.max' => 'مقدار پیشنهادی ویژگی نباید بیشتر از :max کاراکتر باشد.',
        ];
    }
}
