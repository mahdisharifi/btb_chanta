<?php

declare(strict_types=1);

namespace App\Livewire\Reviewer\Task;

use App\Enums\ProductCompletionReviewMode;
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

        $tasks = ProductCompletionTask::query()
            ->select([
                'id', 'public_id', 'product_id', 'assigned_to', 'reviewer_id', 'review_mode',
                'task_type', 'status', 'submitted_at', 'accepted_at', 'started_at', 'due_at',
            ])
            ->with(['product:id,title', 'assignee:id,name'])
            ->when($isQueueTab, function ($query): void {
                $query->whereNull('reviewer_id')
                    ->whereNotNull('assigned_to')
                    ->whereIn('review_mode', [
                        ProductCompletionReviewMode::REVIEWER,
                        ProductCompletionReviewMode::EITHER,
                    ])
                    ->whereIn('status', [
                        ProductCompletionTaskStatus::ACCEPTED,
                        ProductCompletionTaskStatus::IN_PROGRESS,
                        ProductCompletionTaskStatus::SUBMITTED,
                        ProductCompletionTaskStatus::RESUBMITTED,
                    ]);
            }, function ($query): void {
                $query->where('reviewer_id', auth()->id())
                    ->whereIn('status', [
                        ProductCompletionTaskStatus::SUBMITTED,
                        ProductCompletionTaskStatus::RESUBMITTED,
                    ]);
            })
            ->orderByRaw('submitted_at is null')
            ->orderBy('submitted_at')
            ->orderBy('accepted_at')
            ->paginate(20);

        return view('livewire.reviewer.task.index', [
            'tasks' => $tasks,
            'isQueueTab' => $isQueueTab,
        ]);
    }
}
