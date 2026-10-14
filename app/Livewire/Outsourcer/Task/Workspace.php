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
            $task = $this->task();
            $draft = $this->normalizeGalleryOrder($this->draft, $task);
            $task = $service->save(auth()->user(), $task, $draft, $this->draftVersion);
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
            $task = $this->task();
            $draft = $this->normalizeGalleryOrder($this->draft, $task);
            $task = $draftService->save(auth()->user(), $task, $draft, $this->draftVersion);
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

    public function withdrawSubmission(int $submissionId, \App\Services\ProductCompletionSubmissionWorkflow $workflow): void
    {
        $this->resetValidation();
        try {
            $this->syncDraft($workflow->withdraw(auth()->user(), $this->task(), $submissionId));
            $this->dispatch('notify', message: 'ارسال لغو شد؛ اطلاعات و تصاویر شما حفظ شده‌اند و می‌توانید دوباره ویرایش کنید.', type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());
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
                $draft = $this->normalizeGalleryOrder($draft, $this->task());

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

    public function removeUploadedImage(string $mediaPublicId, ProductCompletionDraftService $draftService): void
    {
        $this->resetValidation();
        $task = $this->task();
        Gate::authorize('manageMedia', $task);
        ProductCompletionMedia::query()
            ->where('task_id', $task->getKey())
            ->where('public_id', $mediaPublicId)
            ->where('uploaded_by', auth()->id())
            ->where('status', \App\Enums\ProductCompletionMediaStatus::TEMPORARY)
            ->whereNull('published_product_gallery_id')
            ->firstOrFail();

        $draft = $this->draft;
        $items = data_get($draft, 'gallery.items', []);
        $remaining = array_values(array_filter($items, static fn (array $item): bool =>
            ! (($item['media_public_id'] ?? null) === $mediaPublicId && ($item['action'] ?? '') === 'add')));
        if (count($remaining) === count($items)) {
            return;
        }
        data_set($draft, 'gallery.items', $remaining);
        $draft = $this->normalizeGalleryOrder($draft, $task);
        try {
            // Keep the media file for immutable submission history; only remove its draft reference.
            $this->syncDraft($draftService->save(auth()->user(), $task, $draft, $this->draftVersion));
            $this->dispatch('notify', message: 'تصویر از پیش‌نویس حذف شد.', type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());
        } catch (ProductCompletionDraftVersionConflictException $e) {
            $this->addError('draft_version', $e->getMessage());
        }
    }

    #[\Livewire\Attributes\Renderless]
    public function generateAIPrompt(): array
    {
        $task = $this->task();
        Gate::authorize('updateDraft', $task);
        abort_unless(data_get($task->scope_json, 'short_description') || data_get($task->scope_json, 'full_description'), 403);

        $product = $task->product()->with('category')->firstOrFail();
        $selections = data_get($task->scope_json, 'attributes', false)
            ? ($this->draft['attributes'] ?? [])
            : $product->attributeValues()->get()->groupBy('attribute_id')->map(fn ($values, $id) => [
                'attribute_id' => (int) $id,
                'value_ids' => $values->pluck('id')->all(),
                'proposed_values' => [],
            ])->values()->all();

        try {
            $selections = app(\App\Services\ProductCompletionAttributeValidator::class)->validate($task, $selections, true);
        } catch (ValidationException $e) {
            return ['ready' => false, 'prompt' => '', 'messages' => collect($e->errors())->flatten()->values()->all()];
        }

        $attributeIds = array_column($selections, 'attribute_id');
        $names = Attribute::query()->whereIn('id', $attributeIds)->pluck('name', 'id');
        $values = AttributeValue::query()->whereIn('id', collect($selections)->flatMap(fn ($selection) => $selection['value_ids'])->all())->pluck('value', 'id');
        $lines = [];
        foreach ($selections as $selection) {
            $texts = array_merge(array_map(fn ($id) => $values->get($id), $selection['value_ids']), $selection['proposed_values']);
            if ($texts !== []) {
                $lines[] = '- '.$names->get($selection['attribute_id']).': '.implode('، ', $texts);
            }
        }

        $packagingInfo = $product->quantity_per_packaging > 1
            ? "{$product->quantity_per_packaging} عدد در هر {$product->packaging_unit_label}"
            : 'تکی';
        $title = data_get($task->scope_json, 'title') ? ($this->draft['title'] ?? $product->title) : $product->title;
        $prompt = app(\App\Services\ProductDescriptionPrompt::class)->generate(
            $title ?: '[نام محصول]',
            $product->category?->name ?: '[دسته‌بندی]',
            $product->brand ?: 'نامشخص',
            $packagingInfo,
            $lines !== [] ? implode("\n", $lines) : '- هنوز ویژگی‌هایی تعیین نشده است',
        );

        return ['ready' => true, 'prompt' => $prompt, 'messages' => []];
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
        $task = $this->task()->load(['product.category', 'product.attributeValues.attribute', 'reviewer:id,name']);
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
            ->select(['id', 'public_id', 'task_id', 'uploaded_by', 'original_name', 'status', 'width', 'height', 'published_product_gallery_id'])
            ->get()
            ->keyBy('public_id');

        $existingImages = $task->product->galleries()->where('file_type', 'image')->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')->get()->keyBy('id');

        $openRevision = $task->revisions()
            ->where('open_task_id', $task->getKey())
            ->with('requester:id,name')
            ->first();

        $displaySubmission = $task->submissions()->orderByDesc('version_no')->first();
        $workflow = app(\App\Services\ProductCompletionSubmissionWorkflow::class);
        $withdrawalDeadline = $displaySubmission ? $workflow->deadline($displaySubmission) : null;
        $canWithdraw = $displaySubmission && $workflow->canWithdraw(auth()->user(), $task, $displaySubmission);
        $submittedAttributeNames = $attributes->pluck('name', 'id');
        $submittedAttributeValues = $attributes->flatMap(fn ($attribute) => $attribute->values)->pluck('value', 'id');

        return view('livewire.outsourcer.task.workspace', compact('task', 'attributes', 'media', 'existingImages', 'openRevision',
            'displaySubmission', 'withdrawalDeadline', 'canWithdraw', 'submittedAttributeNames', 'submittedAttributeValues'));
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
        $this->draft = $this->normalizeGalleryOrder($task->current_draft_json ?? [], $task);
        if (is_array($this->draft['gallery'] ?? null)) {
            $this->draft['gallery'] = app(\App\Services\ProductCompletionPrimaryImage::class)->apply($task, $this->draft['gallery']);
        }
        $this->draftVersion = $task->draft_version;
    }

    private function normalizeGalleryOrder(array $draft, ProductCompletionTask $task): array
    {
        // The workspace displays images in array order. Legacy gallery numbers may
        // start at zero, contain gaps, or overlap with newly appended images.
        // Only repair numbering when this task permits changing image order.
        if (! data_get($task->scope_json, 'gallery.reorder', false)) {
            return $draft;
        }

        $items = data_get($draft, 'gallery.items');
        if (! is_array($items)) {
            return $draft;
        }

        $order = 0;
        foreach ($items as &$item) {
            if (! is_array($item)) {
                continue;
            }
            $item['sort_order'] = ($item['action'] ?? '') === 'remove' ? null : ++$order;
        }
        unset($item);
        data_set($draft, 'gallery.items', $items);

        return $draft;
    }

    private function validationErrorSummary(ValidationException $exception): string
    {
        return collect($exception->errors())->flatten()->implode(' | ');
    }

}
