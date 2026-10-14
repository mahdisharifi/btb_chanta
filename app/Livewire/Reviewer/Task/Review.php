<?php

declare(strict_types=1);

namespace App\Livewire\Reviewer\Task;

use App\Enums\ProductCompletionRevisionClassification;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Services\ProductCompletionApprovalService;
use App\Services\ProductCompletionCommissionCalculator;
use App\Services\ProductCompletionCommissionChangeClassifier;
use App\Services\ProductCompletionRevisionService;
use Illuminate\Support\Facades\Gate;
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

    public function mount(ProductCompletionTask $task): void
    {
        Gate::authorize('viewTask', $task);
        Gate::authorize('reviewSubmission', $task);
        $this->taskPublicId = $task->public_id;
        $this->submissionId = $task->last_submission_id;
    }

    public function requestRevision(ProductCompletionRevisionService $service): void
    {
        $submission = $this->submission();
        // A return applies to the whole permitted scope unless specific fields are selected.
        $fields = $this->requestedFields !== []
            ? $this->requestedFields
            : array_keys(array_filter(
                $submission->task->scope_json ?? [],
                static fn ($scope): bool => $scope === true || is_array($scope),
            ));
        $reason = trim($this->revisionReason) !== '' ? $this->revisionReason : $this->reviewNote;
        $service->request(
            auth()->user(),
            $submission,
            $fields,
            $reason,
            ProductCompletionRevisionClassification::from($this->revisionClassification),
        );
        $this->redirectRoute('reviewer.outsourcing.tasks.index', navigate: true);
    }

    public function beginReview(\App\Services\ProductCompletionSubmissionWorkflow $workflow): void
    {
        $task = $this->task();
        if ($task->last_submission_id !== $this->submissionId) {
            $this->addError('submission', 'نسخهٔ ارسالی تغییر کرده است؛ از فهرست تسک‌ها دوباره وارد شوید.');
            return;
        }
        $workflow->beginReview(auth()->user(), $task, $this->submissionId);
    }

    public function approve(ProductCompletionApprovalService $service): void
    {
        $service->approve(
            auth()->user(),
            $this->submission(),
            $this->reviewNote,
            $this->approveFullRate,
            $this->selectiveApproval ? $this->approvedFields : null,
        );
        $this->redirectRoute('reviewer.outsourcing.tasks.index', navigate: true);
    }

    public function reject(ProductCompletionRevisionService $service): void
    {
        // Preserve old action calls while returning work to the contractor for correction.
        $this->requestRevision($service);
    }

    public function render()
    {
        $task = $this->task();
        $submission = $this->submission();

        $attributeSelections = collect(data_get($submission->diff_json, 'attributes.before', []))
            ->concat(data_get($submission->diff_json, 'attributes.after', []))
            ->concat(data_get($submission->payload_json, 'attributes', []))
            ->concat(data_get($task->commission_content_snapshot_json ?? $task->initial_snapshot_json, 'attributes', []));
        $commissionRateUnits = app(ProductCompletionCommissionChangeClassifier::class)->units(
            $task->commission_content_snapshot_json ?? $task->initial_snapshot_json ?? [],
            $submission->payload_json ?? [],
        );
        $hasCorrections = collect($commissionRateUnits)->flatten(1)->contains('kind', 'correction');

        return view('livewire.reviewer.task.review', [
            'task' => $task->load('product:id,title'),
            'submission' => $submission,
            'classifications' => ProductCompletionRevisionClassification::options(),
            'commissionRateUnits' => $commissionRateUnits,
            'hasCorrections' => $hasCorrections,
            'commissionPreview' => app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission),
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
        Gate::authorize('viewSubmission', $submission);

        return $submission;
    }
}
