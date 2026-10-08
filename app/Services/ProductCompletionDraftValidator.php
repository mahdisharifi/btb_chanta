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

        $this->validateSpecificationKeys($validated['specifications'] ?? []);

        return ProductCompletionDraftData::fromValidated(
            $this->canonicalize($task, $validated)
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
            'specifications' => [
                Rule::requiredIf($forSubmission && $this->scopeAllows($task, 'specifications')),
                'sometimes',
                'array',
                'max:'.(int) $validation['specifications_max_items'],
            ],
            'specifications.*' => [
                'nullable',
                'string',
                'max:'.(int) $validation['specification_value_max'],
                $this->withoutControlCharacters(),
            ],
            'attributes' => [
                Rule::requiredIf($forSubmission && $this->scopeAllows($task, 'attributes')),
                'sometimes',
                'array',
                'max:'.(int) $validation['attributes_max'],
            ],
            'attributes.*' => ['required', 'array:attribute_id,value_ids'],
            'attributes.*.attribute_id' => ['required', 'integer', 'min:1', 'distinct'],
            'attributes.*.value_ids' => [
                'required',
                'array',
                'max:'.(int) $validation['attribute_values_max'],
            ],
            'attributes.*.value_ids.*' => ['integer', 'min:1', 'distinct'],
            'seo' => ['sometimes', 'array:title,description,keywords,canonical_url'],
            'seo.title' => [
                Rule::requiredIf($forSubmission && $this->nestedScopeAllows($task, 'seo.title')),
                'nullable',
                'string',
                'max:'.(int) $validation['seo_title_max'],
                $this->withoutControlCharacters(),
            ],
            'seo.description' => [
                Rule::requiredIf($forSubmission && $this->nestedScopeAllows($task, 'seo.description')),
                'nullable',
                'string',
                'max:'.(int) $validation['seo_description_max'],
                $this->withoutControlCharacters(),
            ],
            'seo.keywords' => [
                'nullable',
                'string',
                'max:'.(int) $validation['seo_keywords_max'],
                $this->withoutControlCharacters(),
            ],
            'seo.canonical_url' => ['nullable', 'url:http,https', 'max:255'],
            // Detailed media manifest validation belongs to ProductCompletionMediaService.
            'gallery' => ['sometimes', 'array'],
        ];
    }

    private function canonicalize(ProductCompletionTask $task, array $validated): array
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

        if (array_key_exists('specifications', $validated)) {
            $canonical['specifications'] = $this->sanitizer->specifications($validated['specifications']);
        }

        if (array_key_exists('attributes', $validated)) {
            $canonical['attributes'] = $this->attributeValidator->validate($task, $validated['attributes']);
        }

        if (array_key_exists('seo', $validated)) {
            $canonical['seo'] = $this->canonicalSeo($validated['seo'], $validation);
        }

        if (array_key_exists('gallery', $validated)) {
            $canonical['gallery'] = $validated['gallery'];
        }

        return $canonical;
    }

    private function canonicalSeo(array $seo, array $validation): array
    {
        $canonical = [];

        if (array_key_exists('title', $seo)) {
            $canonical['title'] = $this->sanitizer->plainText(
                $seo['title'],
                (int) $validation['seo_title_max']
            );
        }

        if (array_key_exists('description', $seo)) {
            $canonical['description'] = $this->sanitizer->plainText(
                $seo['description'],
                (int) $validation['seo_description_max'],
                true
            );
        }

        if (array_key_exists('keywords', $seo)) {
            $canonical['keywords'] = $this->sanitizer->keywords($seo['keywords']);
        }

        if (array_key_exists('canonical_url', $seo)) {
            $canonical['canonical_url'] = $seo['canonical_url'];
        }

        return $canonical;
    }

    private function validateSpecificationKeys(array $specifications): void
    {
        $maxLength = (int) config('product_completion.validation.specification_key_max', 100);
        $errors = [];

        foreach (array_keys($specifications) as $key) {
            if (! is_string($key) || trim($key) === '' || mb_strlen($key) > $maxLength) {
                $errors['specifications'] = 'نام مشخصه فنی خالی یا طولانی‌تر از حد مجاز است.';
                break;
            }

            if (preg_match('/[\x00-\x1F\x7F]/u', $key) === 1) {
                $errors['specifications'] = 'نام مشخصه فنی شامل کاراکتر غیرمجاز است.';
                break;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
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

    private function nestedScopeAllows(ProductCompletionTask $task, string $key): bool
    {
        return data_get($task->scope_json, $key, false) === true;
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
            'specifications.required' => 'مشخصات فنی الزامی است.',
            'specifications.array' => 'ساختار مشخصات فنی نامعتبر است.',
            'attributes.required' => 'ویژگی‌های محصول الزامی است.',
            'attributes.array' => 'ساختار ویژگی‌های محصول نامعتبر است.',
            'attributes.*.attribute_id.required' => 'شناسه ویژگی الزامی است.',
            'attributes.*.attribute_id.integer' => 'شناسه ویژگی نامعتبر است.',
            'attributes.*.attribute_id.distinct' => 'یک ویژگی نباید دوبار ارسال شود.',
            'attributes.*.value_ids.array' => 'مقادیر ویژگی باید به‌صورت آرایه ارسال شوند.',
            'attributes.*.value_ids.*.integer' => 'شناسه مقدار ویژگی نامعتبر است.',
            'seo.title.required' => 'عنوان سئو الزامی است.',
            'seo.title.max' => 'عنوان سئو نباید بیشتر از :max کاراکتر باشد.',
            'seo.description.required' => 'توضیحات سئو الزامی است.',
            'seo.description.max' => 'توضیحات سئو نباید بیشتر از :max کاراکتر باشد.',
            'seo.keywords.max' => 'کلمات کلیدی سئو نباید بیشتر از :max کاراکتر باشد.',
            'seo.canonical_url.url' => 'آدرس canonical معتبر نیست.',
        ];
    }
}
