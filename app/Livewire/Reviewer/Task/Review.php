<?php

declare(strict_types=1);

namespace App\Livewire\Reviewer\Task;

use App\Enums\ProductCompletionRevisionClassification;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Exceptions\ProductCompletionRevisionLimitException;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Services\ProductCompletionApprovalService;
use App\Services\ProductCompletionCommissionCalculator;
use App\Services\ProductCompletionCommissionChangeClassifier;
use App\Services\ProductCompletionRevisionService;
use App\Services\ProductCompletionReviewService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.outsourcing')]
class Review extends Component
{
    #[Locked]
    public string $taskPublicId;

    #[Locked]
    public int $submissionId;

    public array $requestedFields = [];
    public string $revisionReason = '';
    public string $reviewNote = '';
    public string $revisionClassification = 'contractor_error';
    public bool $approveFullRate = false;
    public bool $selectiveApproval = false;
    public array $approvedFields = [];
    public bool $extendRevisionAllowance = false;
    public array $reviewPayload = [];
    public array $reviewDecisions = [];

    #[Locked]
    public int $reviewLockVersion = 0;

    #[Locked]
    public string $savedReviewFingerprint = '';

    #[Locked]
    public bool $reviewEditorInitialized = false;

    #[Locked]
    public array $initialSelectedValueIds = [];

    public bool $showReviewEditor = false;
    public bool $reviewEditsSaved = false;

    public function mount(ProductCompletionTask $task): void
    {
        Gate::authorize('viewTask', $task);
        Gate::authorize('reviewSubmission', $task);
        $this->taskPublicId = $task->public_id;
        $this->submissionId = $task->last_submission_id;
        $this->reviewLockVersion = $task->lock_version;
    }

    public function requestRevision(ProductCompletionRevisionService $service): void
    {
        $submission = $this->submission();
        $classification = ProductCompletionRevisionClassification::tryFrom($this->revisionClassification);
        if ($classification === null) {
            throw ValidationException::withMessages(['revisionClassification' => 'نوع درخواست اصلاح را از گزینه‌های مجاز انتخاب کنید.']);
        }
        // A return applies to the whole permitted scope unless specific fields are selected.
        $fields = $this->requestedFields !== []
            ? $this->requestedFields
            : array_keys(array_filter(
                $submission->task->scope_json ?? [],
                static fn ($scope): bool => $scope === true || is_array($scope),
            ));
        $reason = trim($this->revisionReason) !== '' ? $this->revisionReason : $this->reviewNote;
        try {
            $service->request(
                auth()->user(),
                $submission,
                $fields,
                $reason,
                $classification,
                $this->extendRevisionAllowance,
                expectedLockVersion: $this->reviewLockVersion,
            );
        } catch (ProductCompletionRevisionLimitException) {
            throw ValidationException::withMessages([
                'revision_limit' => 'سهمیهٔ اصلاحات تمام شده است. برای اعطای یک فرصت دیگر، گزینهٔ افزایش سهمیهٔ اصلاح را آگاهانه انتخاب کنید؛ سقف مجاز ۱۰۰ اصلاح است.',
            ]);
        }
        $this->redirectRoute('reviewer.outsourcing.tasks.index', navigate: true);
    }

    public function beginReview(\App\Services\ProductCompletionSubmissionWorkflow $workflow): void
    {
        $task = $this->task();
        Gate::authorize('reviewSubmission', $task);
        if ($this->showReviewEditor || $this->reviewEditorInitialized) {
            throw ValidationException::withMessages(['submission' => 'بازبینی با ویرایشگر آغاز شده است. برای شروع دوباره، صفحه را مجدداً بارگذاری کنید.']);
        }
        if ($task->lock_version !== $this->reviewLockVersion) {
            throw ValidationException::withMessages(['lock_version' => 'این بازبینی تغییر کرده است. پیش از شروع، صفحه را دوباره بارگذاری کنید.']);
        }
        if ($task->last_submission_id !== $this->submissionId) {
            $this->addError('submission', 'نسخهٔ ارسالی تغییر کرده است؛ از فهرست تسک‌ها دوباره وارد شوید.');
            return;
        }
        $reviewedTask = $workflow->beginReview(auth()->user(), $task, $this->submissionId);
        $this->reviewLockVersion = $reviewedTask->lock_version;
    }

    public function approve(ProductCompletionApprovalService $service): void
    {
        $submission = $this->submission();
        Gate::authorize('approveSubmission', $submission);
        if ($this->showReviewEditor || $this->reviewEditorInitialized) {
            $this->saveReviewEdits();
            $submission = $this->editableSubmission();
        }
        $service->approve(
            auth()->user(),
            $submission,
            $this->reviewNote,
            $this->approveFullRate,
            $this->selectiveApproval ? $this->approvedFields : null,
            expectedLockVersion: $this->reviewLockVersion,
        );
        $this->redirectRoute('reviewer.outsourcing.tasks.index', navigate: true);
    }

    public function reject(ProductCompletionRevisionService $service): void
    {
        // Preserve old action calls while returning work to the contractor for correction.
        $this->requestRevision($service);
    }

    public function toggleReviewEditor(): void
    {
        $submission = $this->editableSubmission();
        if ($this->showReviewEditor) {
            $this->saveReviewEdits();
            $this->showReviewEditor = false;

            return;
        }

        if (! $this->reviewEditorInitialized) {
            $state = app(ProductCompletionReviewService::class)->initialize($submission);
            $this->reviewPayload = $state['payload'];
            $this->reviewDecisions = $state['decisions'];
            $this->initialSelectedValueIds = collect([
                $submission->payload_json ?? [],
                $state['payload'],
                $submission->approval_selection_json['editor_payload'] ?? [],
                $submission->approval_selection_json['reviewed_payload'] ?? [],
                $submission->approval_selection_json['pricing_payload'] ?? [],
            ])->flatMap(static fn (array $payload): array => $payload['attributes'] ?? [])
                ->pluck('value_ids')->flatten()->map(static fn ($valueId): int => (int) $valueId)
                ->filter(static fn (int $valueId): bool => $valueId > 0)->unique()->values()->all();
            $this->reviewEditorInitialized = true;
        }
        $this->showReviewEditor = true;
    }

    public function saveReviewEdits(): void
    {
        $submission = $this->editableSubmission();
        Gate::authorize('approveSubmission', $submission);
        $fingerprint = $this->reviewFingerprint();
        if ($submission->task->lock_version !== $this->reviewLockVersion) {
            throw ValidationException::withMessages(['lock_version' => 'این بازبینی تغییر کرده است. پیش از ذخیره، صفحه را دوباره بارگذاری کنید.']);
        }
        if ($this->savedReviewFingerprint !== '' && hash_equals($this->savedReviewFingerprint, $fingerprint)) {
            $this->reviewEditsSaved = true;

            return;
        }
        $saved = app(ProductCompletionReviewService::class)->save(
            auth()->user(), $submission, $this->reviewLockVersion, $this->reviewPayload, $this->reviewDecisions,
        );
        $this->reviewLockVersion = $saved->task->lock_version;
        $this->savedReviewFingerprint = $fingerprint;
        $this->reviewEditorInitialized = true;
        $this->reviewEditsSaved = true;
        $this->resetValidation();
        session()->flash('success', 'ویرایش‌ها و تصمیم‌های بازبینی ذخیره شد.');
    }

    public function updatedReviewPayload(): void
    {
        $this->reviewEditsSaved = false;
    }

    public function updatedReviewDecisions(): void
    {
        $this->reviewEditsSaved = false;
    }

    public function addReviewAttribute(int $id): void
    {
        $submission = $this->editableSubmission();
        $this->authorizeAttributeScope($submission->task);
        $attribute = $this->categoryAttributes($submission->task)->whereKey($id)->firstOrFail();
        if (collect($this->reviewPayload['attributes'] ?? [])->contains('attribute_id', $attribute->id)) {
            return;
        }
        if (count($this->reviewPayload['attributes'] ?? []) >= (int) config('product_completion.validation.attributes_max', 100)) {
            throw ValidationException::withMessages(['reviewPayload.attributes' => 'تعداد ویژگی‌ها از حد مجاز بیشتر است.']);
        }
        $this->reviewPayload['attributes'][] = ['attribute_id' => $attribute->id, 'value_ids' => [], 'proposed_values' => []];
        $this->reviewDecisions['attribute:'.$attribute->id] = 'approve';
        $this->reviewEditsSaved = false;
    }

    public function removeReviewAttribute(int $index): void
    {
        $this->reviewAttribute($index);
        $attributeId = (int) $this->reviewPayload['attributes'][$index]['attribute_id'];
        unset($this->reviewPayload['attributes'][$index]);
        $this->reviewPayload['attributes'] = array_values($this->reviewPayload['attributes']);
        $this->reviewDecisions['attribute:'.$attributeId] = 'approve';
        $this->syncReviewAttributeDecisions($attributeId);
        $this->reviewEditsSaved = false;
        $this->dispatch('review-editor-focus');
    }

    public function toggleReviewAttributeValue(int $index, int $valueId): void
    {
        $attribute = $this->reviewAttribute($index);
        $attribute->values()->whereKey($valueId)->firstOrFail();
        $selected = array_map('intval', $this->reviewPayload['attributes'][$index]['value_ids'] ?? []);
        $this->reviewPayload['attributes'][$index]['value_ids'] = in_array($valueId, $selected, true)
            ? array_values(array_diff($selected, [$valueId]))
            : ($attribute->is_multiple ? [...$selected, $valueId] : [$valueId]);
        if (! $attribute->is_multiple) {
            $this->reviewPayload['attributes'][$index]['proposed_values'] = [];
        }
        $this->reviewEditsSaved = false;
    }

    public function addReviewProposedValue(int $index): void
    {
        $attribute = $this->reviewAttribute($index);
        $proposals = $this->reviewPayload['attributes'][$index]['proposed_values'] ?? [];
        if (count($proposals) >= (int) config('product_completion.validation.attribute_proposed_values_max', 10)) {
            throw ValidationException::withMessages(['reviewPayload.attributes' => 'تعداد مقدارهای پیشنهادی از حد مجاز بیشتر است.']);
        }
        if (! $attribute->is_multiple) {
            $this->reviewPayload['attributes'][$index]['value_ids'] = [];
            $proposals = [];
        }
        $this->reviewPayload['attributes'][$index]['proposed_values'] = [...$proposals, ''];
        $this->reviewEditsSaved = false;
    }

    public function removeReviewProposedValue(int $index, int $proposalIndex): void
    {
        $this->reviewAttribute($index);
        unset($this->reviewPayload['attributes'][$index]['proposed_values'][$proposalIndex]);
        $this->reviewPayload['attributes'][$index]['proposed_values'] = array_values(
            $this->reviewPayload['attributes'][$index]['proposed_values'] ?? [],
        );
        $this->reviewEditsSaved = false;
        $this->dispatch('review-editor-focus');
    }

    public function render()
    {
        $task = $this->task();
        $submission = $this->submission();
        $selection = $submission->approval_selection_json;
        $commissionPreviewPendingReview = is_array($selection)
            && ($selection['mode'] ?? null) === 'selective'
            && array_intersect(['schema_version', 'reviewed_payload', 'editor_payload', 'pricing_payload', 'media_items'], array_keys($selection)) === [];
        $previewSubmission = $submission;
        if ($commissionPreviewPendingReview) {
            $previewSubmission = clone $submission;
            $previewSubmission->approval_selection_json = null;
        }
        $pricingPayload = $previewSubmission->pricingPayload();
        $commissionPreview = app(ProductCompletionCommissionCalculator::class)->calculate($task, $previewSubmission);

        $attributeSelections = collect(data_get($submission->diff_json, 'attributes.before', []))
            ->concat(data_get($submission->diff_json, 'attributes.after', []))
            ->concat(data_get($submission->payload_json, 'attributes', []))
            ->concat($pricingPayload['attributes'] ?? [])
            ->concat(data_get($task->commission_content_snapshot_json ?? $task->initial_snapshot_json, 'attributes', []));
        $commissionRateUnits = app(ProductCompletionCommissionChangeClassifier::class)->units(
            $task->commission_content_snapshot_json ?? $task->initial_snapshot_json ?? [],
            $pricingPayload,
        );
        $hasCorrections = collect($commissionRateUnits)->flatten(1)->contains('kind', 'correction');
        $editorAttributes = collect();
        $reviewMediaItems = collect();
        if ($this->showReviewEditor) {
            $this->editableSubmission();
            if (data_get($task->scope_json, 'attributes', false) === true) {
                $editorAttributes = $this->categoryAttributes($task)
                    ->get(['attributes.id', 'attributes.name', 'attributes.unit', 'attributes.is_multiple'])->keyBy('id');
                $activeAttributeIds = collect($this->reviewPayload['attributes'] ?? [])
                    ->pluck('attribute_id')->map(static fn ($attributeId): int => (int) $attributeId)
                    ->intersect($editorAttributes->keys())->unique()->values();
                $editorAttributes->only($activeAttributeIds->all())
                    ->load(['values' => function ($query): void {
                        $query->orderByDesc(\Illuminate\Support\Facades\DB::raw('(select count(*) from attribute_product inner join products on products.id = attribute_product.product_id where attribute_product.attribute_value_id = attribute_values.id and products.deleted_at is null)'))
                            ->orderBy('attribute_values.value')->orderBy('attribute_values.id')->limit(50);
                    }]);
                $selectedValueIds = collect($this->reviewPayload['attributes'] ?? [])->pluck('value_ids')->flatten()
                    ->concat($this->initialSelectedValueIds)->unique()->values();
                $selectedValues = AttributeValue::query()
                    ->whereIn('attribute_id', $activeAttributeIds)
                    ->whereIn('id', $selectedValueIds)
                    ->get(['id', 'attribute_id', 'value'])->groupBy('attribute_id');
                foreach ($editorAttributes as $attribute) {
                    $popularValues = $attribute->relationLoaded('values') ? $attribute->getRelation('values') : collect();
                    $attribute->setRelation('values', $popularValues
                        ->concat($selectedValues->get($attribute->id, collect()))->unique('id')->values());
                }
            }
            $reviewMediaItems = $submission->mediaItems()->with(['media', 'productGallery'])->orderBy('sort_order')->orderBy('id')->get();
        }

        return view('livewire.reviewer.task.review', [
            'task' => $task->load('product:id,title,category_id'),
            'submission' => $submission,
            'classifications' => ProductCompletionRevisionClassification::options(),
            'commissionRateUnits' => $commissionRateUnits,
            'hasCorrections' => $hasCorrections,
            'editorAttributes' => $editorAttributes,
            'reviewMediaItems' => $reviewMediaItems,
            'commissionPreview' => $commissionPreview,
            'commissionPreviewPendingReview' => $commissionPreviewPendingReview,
            'fieldLabels' => [
                'title' => 'عنوان محصول',
                'short_description' => 'توضیحات کوتاه',
                'full_description' => 'توضیحات کامل',
                'attributes' => 'ویژگی‌های محصول',
                'gallery' => 'تصاویر محصول',
            ],
            'attributeNames' => Attribute::query()->whereIn('id', $attributeSelections->pluck('attribute_id'))->pluck('name', 'id'),
            'attributeValues' => AttributeValue::query()->whereIn('id', $attributeSelections->pluck('value_ids')->flatten())->pluck('value', 'id'),
            'existingImages' => $task->product->galleries()->where('file_type', 'image')->get()->keyBy('id'),
            'uploadedImages' => $task->media()->get()->keyBy('public_id'),
        ]);
    }

    private function task(): ProductCompletionTask
    {
        $task = ProductCompletionTask::query()->where('public_id', $this->taskPublicId)->firstOrFail();
        Gate::authorize('viewTask', $task);

        return $task;
    }

    private function submission(): ProductCompletionSubmission
    {
        $task = $this->task();
        $submission = ProductCompletionSubmission::query()->findOrFail($this->submissionId);
        abort_unless($submission->task_id === $task->getKey(), 403);
        $submission->setRelation('task', $task);
        Gate::authorize('viewSubmission', $submission);

        return $submission;
    }

    private function editableSubmission(): ProductCompletionSubmission
    {
        $submission = $this->submission();
        Gate::authorize('reviewSubmission', $submission);
        abort_unless($submission->task->reviewer_id === auth()->id(), 403);
        if ($submission->task->last_submission_id !== $submission->getKey()
            || ! $submission->task->status->canBeReviewed()
            || $submission->status !== ProductCompletionSubmissionStatus::UNDER_REVIEW) {
            throw ValidationException::withMessages(['submission' => 'فقط آخرین ارسال در حال بازبینی قابل ویرایش است.']);
        }

        return $submission;
    }

    private function authorizeAttributeScope(ProductCompletionTask $task): void
    {
        abort_unless(data_get($task->scope_json, 'attributes', false) === true, 403);
    }

    private function categoryAttributes(ProductCompletionTask $task): \Illuminate\Database\Eloquent\Builder
    {
        return Attribute::query()->whereHas('categories', fn ($query) => $query->where('categories.id', $task->product->category_id))
            ->orderBy('attributes.name')->orderBy('attributes.id');
    }

    private function reviewAttribute(int $index): Attribute
    {
        $submission = $this->editableSubmission();
        $this->authorizeAttributeScope($submission->task);
        $attributeId = $this->reviewPayload['attributes'][$index]['attribute_id'] ?? null;

        return $this->categoryAttributes($submission->task)->whereKey($attributeId)->firstOrFail();
    }

    private function syncReviewAttributeDecisions(int $removedId): void
    {
        $submission = $this->editableSubmission();
        $known = collect([
            $submission->task->initial_snapshot_json ?? [],
            $submission->task->commission_content_snapshot_json ?? [],
            $submission->payload_json ?? [],
        ])->flatMap(static fn (array $payload): array => $payload['attributes'] ?? [])
            ->contains('attribute_id', $removedId);
        if (! $known && ! array_key_exists('attribute:'.$removedId, $submission->approval_selection_json['decisions'] ?? [])) {
            unset($this->reviewDecisions['attribute:'.$removedId]);
        }
    }

    private function reviewFingerprint(): string
    {
        try {
            return hash('sha256', json_encode([$this->reviewPayload, $this->reviewDecisions], JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            throw ValidationException::withMessages(['reviewPayload' => 'ساختار محتوای بازبینی معتبر نیست.']);
        }
    }
}
