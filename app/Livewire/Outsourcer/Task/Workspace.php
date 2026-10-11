<?php

declare(strict_types=1);

namespace App\Livewire\Outsourcer\Task;

use App\Exceptions\ProductCompletionDraftVersionConflictException;
use App\Models\Attribute;
use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionTask;
use App\Services\ProductCompletionDraftService;
use App\Services\ProductCompletionMediaService;
use App\Services\ProductCompletionSubmissionService;
use App\Services\ProductCompletionTaskService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.outsourcing')]
class Workspace extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $taskPublicId;

    public array $draft = [];
    public int $draftVersion = 0;
    public string $specificationsText = '';

    /** @var array<int, string> */
    public array $newAttributeValues = [];

    public $upload = null;

    public function mount(ProductCompletionTask $task): void
    {
        Gate::authorize('viewTask', $task);
        $this->taskPublicId = $task->public_id;
        $this->syncDraft($task);
    }

    public function accept(ProductCompletionTaskService $service): void
    {
        $this->syncDraft($service->accept(auth()->user(), $this->task()));
    }

    public function start(ProductCompletionTaskService $service): void
    {
        $this->syncDraft($service->start(auth()->user(), $this->task()));
    }

    public function resumeRevision(ProductCompletionTaskService $service): void
    {
        $this->syncDraft($service->resumeRevision(auth()->user(), $this->task()));
    }

    public function saveDraft(ProductCompletionDraftService $service): void
    {
        try {
            $this->syncSpecificationsToDraft();
            $task = $service->save(auth()->user(), $this->task(), $this->draft, $this->draftVersion);
            $this->syncDraft($task);
            $this->dispatch('notify', message: 'پیش‌نویس ذخیره شد.', type: 'success');
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: 'ذخیره پیش‌نویس انجام نشد: '.$this->validationErrorSummary($e), type: 'error');
        } catch (ProductCompletionDraftVersionConflictException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    public function submit(ProductCompletionSubmissionService $service): void
    {
        try {
            $this->syncSpecificationsToDraft();
            $submission = $service->submit(
                auth()->user(),
                $this->task(),
                $this->draftVersion,
                (string) Str::uuid(),
            );
            $this->syncDraft($submission->task()->firstOrFail());
            $this->dispatch('notify', message: 'نسخه برای بررسی ارسال شد.', type: 'success');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            // نقشه‌برداری فیلدهای nested به نام‌های فارسی
            $fieldLabels = [
                'title'             => 'عنوان',
                'short_description' => 'توضیحات کوتاه',
                'full_description'  => 'توضیحات کامل',
                'specifications'    => 'مشخصات فنی',
                'attributes'        => 'ویژگی‌ها',
                'seo.title'         => 'عنوان SEO',
                'seo.description'   => 'توضیحات SEO',
                'seo.keywords'      => 'کلمات کلیدی SEO',
                'gallery'           => 'تصاویر',
                'draft_version'     => 'نسخه پیش‌نویس',
                'idempotency_token' => 'شناسه ارسال',
                'submission'        => 'ارسال',
                'commissionRuleId'  => 'قانون پورسانت',
            ];

            $messages = [];
            foreach ($errors as $field => $fieldErrors) {
                $label = $fieldLabels[$field] ?? $field;
                foreach ($fieldErrors as $error) {
                    $messages[] = "({$label}): {$error}";
                }
            }

            $summary = implode(' | ', $messages);
            $this->dispatch('notify', message: 'ارسال انجام نشد: '.$summary, type: 'error');
        }
    }

    public function uploadMedia(
        ProductCompletionMediaService $mediaService,
        ProductCompletionDraftService $draftService,
    ): void {
        $this->validate([
            'upload' => ['required', 'image', 'max:'.(int) config('product_completion.media.max_file_size_kb', 2048)],
        ], [
            'upload.required' => 'انتخاب تصویر الزامی است.',
            'upload.image' => 'فایل انتخاب‌شده تصویر معتبر نیست.',
            'upload.max' => 'حجم تصویر بیشتر از حد مجاز است.',
        ]);
        $task = $this->task();
        $media = $mediaService->stage(auth()->user(), $task, $this->upload);
        $items = data_get($this->draft, 'gallery.items', []);
        $finalCount = count(array_filter($items, static fn (array $item): bool => ($item['action'] ?? '') !== 'remove'));
        $items[] = [
            'media_public_id' => $media->public_id,
            'product_gallery_id' => null,
            'action' => 'add',
            'sort_order' => $finalCount + 1,
            'is_primary' => $finalCount === 0,
            'is_seo_image' => false,
            'title' => $this->draft['title'] ?? null,
            'alt_text' => $this->draft['title'] ?? null,
        ];
        data_set($this->draft, 'gallery.items', $items);
        $task = $draftService->save(auth()->user(), $task, $this->draft, $this->draftVersion);
        $this->upload = null;
        $this->syncDraft($task);
    }

    public function selectPrimary(int $index): void
    {
        foreach (data_get($this->draft, 'gallery.items', []) as $itemIndex => $item) {
            data_set($this->draft, "gallery.items.{$itemIndex}.is_primary", $itemIndex === $index && ($item['action'] ?? '') !== 'remove');
        }
    }

    public function selectSeo(int $index): void
    {
        foreach (data_get($this->draft, 'gallery.items', []) as $itemIndex => $item) {
            data_set($this->draft, "gallery.items.{$itemIndex}.is_seo_image", $itemIndex === $index && ($item['action'] ?? '') !== 'remove');
        }
    }

    public function toggleAttribute(int $attributeId, int $valueId, bool $multiple): void
    {
        $attributes = collect($this->draft['attributes'] ?? [])->keyBy('attribute_id');
        $selection = $attributes->get($attributeId, [
            'attribute_id' => $attributeId,
            'value_ids' => [],
            'proposed_values' => [],
        ]);
        $values = array_map('intval', $selection['value_ids'] ?? []);
        $selection['value_ids'] = $multiple
            ? (in_array($valueId, $values, true)
                ? array_values(array_diff($values, [$valueId]))
                : array_values(array_unique([...$values, $valueId])))
            : [$valueId];

        if (! $multiple) {
            $selection['proposed_values'] = [];
        }

        $attributes->put($attributeId, $selection);
        $this->draft['attributes'] = $attributes->values()->all();
    }

    public function addProposedAttributeValue(int $attributeId, bool $multiple): void
    {
        $value = trim((string) ($this->newAttributeValues[$attributeId] ?? ''));
        $maxLength = (int) config('product_completion.validation.attribute_proposed_value_max', 255);

        if ($value === '' || mb_strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
            throw ValidationException::withMessages([
                "newAttributeValues.{$attributeId}" => 'مقدار جدید ویژگی معتبر نیست.',
            ]);
        }

        $attributes = collect($this->draft['attributes'] ?? [])->keyBy('attribute_id');
        $selection = $attributes->get($attributeId, [
            'attribute_id' => $attributeId,
            'value_ids' => [],
            'proposed_values' => [],
        ]);
        $proposedValues = array_values(array_unique([
            ...($selection['proposed_values'] ?? []),
            $value,
        ]));

        $selection['proposed_values'] = $multiple ? $proposedValues : [$value];

        if (! $multiple) {
            $selection['value_ids'] = [];
        }

        $attributes->put($attributeId, $selection);
        $this->draft['attributes'] = $attributes->values()->all();
        $this->newAttributeValues[$attributeId] = '';
    }

    public function removeProposedAttributeValue(int $attributeId, string $value): void
    {
        $attributes = collect($this->draft['attributes'] ?? [])->keyBy('attribute_id');
        $selection = $attributes->get($attributeId);

        if ($selection === null) {
            return;
        }

        $selection['proposed_values'] = array_values(array_filter(
            $selection['proposed_values'] ?? [],
            static fn (string $proposedValue): bool => $proposedValue !== $value,
        ));
        $attributes->put($attributeId, $selection);
        $this->draft['attributes'] = $attributes->values()->all();
    }

    public function render()
    {
        $task = $this->task();
        $categoryId = (int) data_get($task->initial_snapshot_json, 'category_id');
        $attributes = data_get($task->scope_json, 'attributes', false)
            ? Attribute::query()
                ->join('attribute_category', function ($join) use ($categoryId): void {
                    $join->on('attribute_category.attribute_id', '=', 'attributes.id')
                        ->where('attribute_category.category_id', '=', $categoryId);
                })
                ->with('values:id,attribute_id,value')
                ->select([
                    'attributes.id',
                    'attributes.name',
                    'attributes.is_multiple',
                    'attribute_category.display_order',
                    'attribute_category.is_required',
                ])
                ->orderBy('attribute_category.display_order')
                ->orderBy('attributes.id')
                ->get()
            : collect();
        $media = ProductCompletionMedia::query()
            ->where('task_id', $task->getKey())
            ->select(['id', 'public_id', 'task_id', 'original_name', 'status', 'width', 'height'])
            ->get()
            ->keyBy('public_id');

        return view('livewire.outsourcer.task.workspace', compact('task', 'attributes', 'media'));
    }

    private function task(): ProductCompletionTask
    {
        $task = ProductCompletionTask::query()->where('public_id', $this->taskPublicId)->firstOrFail();
        Gate::authorize('viewTask', $task);

        return $task;
    }

    private function syncDraft(ProductCompletionTask $task): void
    {
        $task->refresh();
        $this->draft = $task->current_draft_json ?? [];
        $this->draftVersion = $task->draft_version;
        $this->specificationsText = $this->formatSpecificationsForEditing(
            data_get($this->draft, 'specifications', [])
        );
    }

    private function syncSpecificationsToDraft(): void
    {
        $specifications = [];
        $lines = preg_split('/\R/u', $this->specificationsText) ?: [];

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s*[:：]\s*/u', $line, 2);

            if ($parts === false || count($parts) !== 2 || trim($parts[0]) === '') {
                throw ValidationException::withMessages([
                    'specifications' => 'هر مشخصه فنی را در یک خط و با فرمت «نام: مقدار» وارد کنید. خط '.($lineNumber + 1).' نامعتبر است.',
                ]);
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if (array_key_exists($key, $specifications)) {
                throw ValidationException::withMessages([
                    'specifications' => 'نام مشخصه «'.$key.'» بیش از یک‌بار وارد شده است.',
                ]);
            }

            $specifications[$key] = $value;
        }

        $this->draft['specifications'] = $specifications;
    }

    private function formatSpecificationsForEditing(mixed $specifications): string
    {
        if (! is_array($specifications)) {
            return '';
        }

        $lines = [];

        foreach ($specifications as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $lines[] = trim($key).': '.trim((string) $value);
            }
        }

        return implode(PHP_EOL, $lines);
    }
}
