<?php

declare(strict_types=1);

namespace App\Livewire\Outsourcer\Task;

use App\Exceptions\ProductCompletionDraftVersionConflictException;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionTask;
use App\Support\AttributeValueIdentity;
use App\Services\ProductCompletionDraftService;
use App\Services\ProductCompletionMediaService;
use App\Services\ProductCompletionSubmissionService;
use App\Services\ProductCompletionTaskService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('components.layouts.outsourcing')]
class Workspace extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $taskPublicId;

    public array $draft = [];
    public int $draftVersion = 0;

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
        $this->resetValidation();
        try {
            $task = $service->save(auth()->user(), $this->task(), $this->draft, $this->draftVersion);
            $this->syncDraft($task);
            $this->dispatch('notify', message: 'پیش‌نویس ذخیره شد.', type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());
            $this->dispatch('notify', message: 'ذخیره پیش‌نویس انجام نشد: '.$this->validationErrorSummary($e), type: 'error');
        } catch (ProductCompletionDraftVersionConflictException $e) {
            $this->addError('draft_version', $e->getMessage());
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    public function submit(ProductCompletionSubmissionService $service, ProductCompletionDraftService $draftService): void
    {
        $this->resetValidation();
        try {
            $task = $draftService->save(auth()->user(), $this->task(), $this->draft, $this->draftVersion);
            $this->syncDraft($task);
            $submission = $service->submit(
                auth()->user(),
                $this->task(),
                $this->draftVersion,
                (string) Str::uuid(),
            );
            $this->syncDraft($submission->task()->firstOrFail());
            $this->dispatch('notify', message: 'نسخه برای بررسی ارسال شد.', type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());
            $this->dispatch('notify', message: 'ارسال انجام نشد: '.$this->validationErrorSummary($e), type: 'error');
        } catch (ProductCompletionDraftVersionConflictException $e) {
            $this->addError('draft_version', $e->getMessage());
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
        $this->resetValidation();
        $media = null;
        try {
            $items = data_get($this->draft, 'gallery.items', []);
            $finalCount = count(array_filter($items, static fn (array $item): bool => ($item['action'] ?? '') !== 'remove'));
            if ($finalCount >= (int) config('product_completion.media.max_images', 10)) {
                throw ValidationException::withMessages(['upload' => 'حداکثر تعداد تصاویر محصول تکمیل شده است.']);
            }

            $task = DB::transaction(function () use ($mediaService, $draftService, $items, $finalCount, &$media) {
                $media = $mediaService->stage(auth()->user(), $this->task(), $this->upload);
                $items[] = [
                    'media_public_id' => $media->public_id,
                    'product_gallery_id' => null,
                    'action' => 'add',
                    'sort_order' => $finalCount + 1,
                    'is_primary' => $finalCount === 0,
                    'title' => $this->draft['title'] ?? null,
                    'alt_text' => $this->draft['title'] ?? null,
                ];
                $draft = $this->draft;
                data_set($draft, 'gallery.items', $items);

                return $draftService->save(auth()->user(), $this->task(), $draft, $this->draftVersion);
            });
        } catch (Throwable $e) {
            if ($media !== null) {
                Storage::disk($media->disk)->delete($media->temporary_path);
            }
            if ($e instanceof ValidationException) {
                $this->setErrorBag($e->errors());
                return;
            }
            if ($e instanceof ProductCompletionDraftVersionConflictException) {
                $this->addError('draft_version', $e->getMessage());
                return;
            }
            throw $e;
        }

        $this->upload = null;
        $this->syncDraft($task);
        $this->dispatch('notify', message: 'تصویر در پیش‌نویس ذخیره شد.', type: 'success');
    }

    public function selectPrimary(int $index): void
    {
        foreach (data_get($this->draft, 'gallery.items', []) as $itemIndex => $item) {
            data_set($this->draft, "gallery.items.{$itemIndex}.is_primary", $itemIndex === $index && ($item['action'] ?? '') !== 'remove');
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

    public function proposeAttributeValue(int $attributeId, bool $multiple, string $value): array
    {
        $this->newAttributeValues[$attributeId] = $value;

        try {
            $this->addProposedAttributeValue($attributeId, $multiple);
        } catch (ValidationException $exception) {
            return [
                'status' => 'error',
                'message' => collect($exception->errors())->flatten()->first(),
                'values' => AttributeValue::where('attribute_id', $attributeId)->get(['id', 'value'])->toArray(),
            ];
        }

        return ['status' => 'added'];
    }

    public function addProposedAttributeValue(int $attributeId, bool $multiple): void
    {
        $this->resetValidation("newAttributeValues.{$attributeId}");
        $value = trim((string) ($this->newAttributeValues[$attributeId] ?? ''));
        $maxLength = (int) config('product_completion.validation.attribute_proposed_value_max', 255);

        if ($value === '' || mb_strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
            throw ValidationException::withMessages([
                "newAttributeValues.{$attributeId}" => 'مقدار جدید ویژگی معتبر نیست.',
            ]);
        }

        if (AttributeValueIdentity::exists($attributeId, $value)) {
            $this->dispatch('attribute-value-duplicate', attributeId: $attributeId, value: $value);
            throw ValidationException::withMessages([
                "newAttributeValues.{$attributeId}" => 'این مقدار از قبل وجود دارد؛ آن را از فهرست مقادیر انتخاب کنید.',
            ]);
        }

        $attributes = collect($this->draft['attributes'] ?? [])->keyBy('attribute_id');
        $selection = $attributes->get($attributeId, [
            'attribute_id' => $attributeId,
            'value_ids' => [],
            'proposed_values' => [],
        ]);
        foreach ($selection['proposed_values'] ?? [] as $proposedValue) {
            if (AttributeValueIdentity::normalize($proposedValue) === AttributeValueIdentity::normalize($value)) {
                $this->dispatch('attribute-value-duplicate', attributeId: $attributeId, value: $value);
                throw ValidationException::withMessages([
                    "newAttributeValues.{$attributeId}" => 'این مقدار قبلاً پیشنهاد شده است.',
                ]);
            }
        }
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
        $this->dispatch('attribute-value-proposed', attributeId: $attributeId);
    }

    public function removeAttributeValue(int $attributeId, int $valueId): void
    {
        foreach ($this->draft['attributes'] ?? [] as $index => $selection) {
            if ((int) ($selection['attribute_id'] ?? 0) === $attributeId) {
                $this->draft['attributes'][$index]['value_ids'] = array_values(array_filter(
                    $selection['value_ids'] ?? [],
                    static fn ($id): bool => (int) $id !== $valueId,
                ));
            }
        }
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
        $task = $this->task()->load(['product.category', 'reviewer:id,name']);
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

        $existingImages = $task->product->galleries()->where('file_type', 'image')->get()->keyBy('id');

        $openRevision = $task->revisions()
            ->where('open_task_id', $task->getKey())
            ->with('requester:id,name')
            ->first();

        return view('livewire.outsourcer.task.workspace', compact('task', 'attributes', 'media', 'existingImages', 'openRevision'));
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
    }

    private function validationErrorSummary(ValidationException $exception): string
    {
        return collect($exception->errors())->flatten()->implode(' | ');
    }

}
