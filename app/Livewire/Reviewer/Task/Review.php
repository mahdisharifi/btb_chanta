<?php

declare(strict_types=1);

namespace App\Livewire\Reviewer\Task;

use App\Enums\ProductCompletionRevisionClassification;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Services\ProductCompletionApprovalService;
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

    public array $requestedFields = [];
    public string $revisionReason = '';
    public string $reviewNote = '';
    public string $revisionClassification = 'contractor_error';
    public bool $approveFullRate = false;

    public function mount(ProductCompletionTask $task): void
    {
        Gate::authorize('viewTask', $task);
        Gate::authorize('reviewSubmission', $task);
        $this->taskPublicId = $task->public_id;
    }

    public function requestRevision(ProductCompletionRevisionService $service): void
    {
        $submission = $this->submission();
        $service->request(
            auth()->user(),
            $submission,
            $this->requestedFields,
            $this->revisionReason,
            ProductCompletionRevisionClassification::from($this->revisionClassification),
        );
        $this->redirectRoute('reviewer.outsourcing.tasks.index', navigate: true);
    }

    public function approve(ProductCompletionApprovalService $service): void
    {
        $service->approve(
            auth()->user(),
            $this->submission(),
            $this->reviewNote,
            $this->approveFullRate,
        );
        $this->redirectRoute('reviewer.outsourcing.tasks.index', navigate: true);
    }

    public function reject(ProductCompletionApprovalService $service): void
    {
        $service->reject(auth()->user(), $this->submission(), $this->reviewNote);
        $this->redirectRoute('reviewer.outsourcing.tasks.index', navigate: true);
    }

    public function render()
    {
        $task = $this->task();
        $submission = $this->submission();

        return view('livewire.reviewer.task.review', [
            'task' => $task->load('product:id,title'),
            'submission' => $submission,
            'classifications' => ProductCompletionRevisionClassification::options(),
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
        $submission = ProductCompletionSubmission::query()->findOrFail($task->last_submission_id);
        Gate::authorize('viewSubmission', $submission);

        return $submission;
    }
}
