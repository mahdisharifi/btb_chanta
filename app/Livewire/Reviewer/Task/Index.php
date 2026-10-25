<?php

declare(strict_types=1);

namespace App\Livewire\Reviewer\Task;

use App\Enums\ProductCompletionReviewMode;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Models\ProductCompletionTask;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.outsourcing')]
class Index extends Component
{
    use WithPagination;

    public string $tab = 'queue';

    public function mount(): void
    {
        Gate::authorize('viewAny', ProductCompletionTask::class);
    }

    public function updatedTab(): void
    {
        if (! in_array($this->tab, ['queue', 'mine'], true)) {
            $this->tab = 'queue';
        }

        $this->resetPage();
    }

    public function render()
    {
        $isQueueTab = $this->tab === 'queue';
        $reviewableTaskStatuses = [
            ProductCompletionTaskStatus::SUBMITTED->value,
            ProductCompletionTaskStatus::RESUBMITTED->value,
        ];
        $reviewableSubmissionStatuses = [
            ProductCompletionSubmissionStatus::SUBMITTED->value,
            ProductCompletionSubmissionStatus::UNDER_REVIEW->value,
        ];

        $tasks = ProductCompletionTask::query()
            ->select([
                'id', 'public_id', 'product_id', 'assigned_to', 'reviewer_id', 'review_mode', 'last_submission_id',
                'task_type', 'status', 'submitted_at', 'accepted_at', 'started_at', 'due_at',
            ])
            ->with(['product:id,title', 'assignee:id,name'])
            ->when($isQueueTab, function ($query) use ($reviewableTaskStatuses, $reviewableSubmissionStatuses): void {
                $query->whereNull('reviewer_id')
                    ->whereNotNull('assigned_to')
                    ->whereIn('review_mode', [
                        ProductCompletionReviewMode::REVIEWER,
                        ProductCompletionReviewMode::EITHER,
                    ])
                    ->where(function ($query) use ($reviewableTaskStatuses, $reviewableSubmissionStatuses): void {
                        $query->whereIn('status', [
                            ProductCompletionTaskStatus::ACCEPTED->value,
                            ProductCompletionTaskStatus::IN_PROGRESS->value,
                        ])->orWhere(function ($query) use ($reviewableTaskStatuses, $reviewableSubmissionStatuses): void {
                            $query->whereIn('status', $reviewableTaskStatuses)
                                ->whereNotNull('last_submission_id')
                                ->whereHas('lastSubmission', fn ($submission) => $submission
                                    ->whereIn('status', $reviewableSubmissionStatuses));
                        });
                    });
            }, function ($query) use ($reviewableTaskStatuses, $reviewableSubmissionStatuses): void {
                $query->where('reviewer_id', auth()->id())
                    ->whereIn('status', $reviewableTaskStatuses)
                    ->whereNotNull('last_submission_id')
                    ->whereHas('lastSubmission', fn ($submission) => $submission
                        ->whereIn('status', $reviewableSubmissionStatuses));
            })
            ->orderByRaw('submitted_at is null')
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(20);

        return view('livewire.reviewer.task.index', [
            'tasks' => $tasks,
            'isQueueTab' => $isQueueTab,
        ]);
    }

    public function beginReview(string $taskPublicId, int $submissionId, \App\Services\ProductCompletionSubmissionWorkflow $workflow): void
    {
        $task = ProductCompletionTask::query()->where('public_id', $taskPublicId)->firstOrFail();
        $workflow->beginReview(auth()->user(), $task, $submissionId);
        $this->redirectRoute('reviewer.outsourcing.tasks.review', $task, navigate: true);
    }
}
