<?php

declare(strict_types=1);

namespace App\Livewire\Outsourcer\Task;

use App\Models\ProductCompletionTask;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.outsourcing')]
class Submissions extends Component
{
    #[Locked]
    public string $taskPublicId;

    public function mount(ProductCompletionTask $task): void
    {
        Gate::authorize('viewTask', $task);
        $this->taskPublicId = $task->public_id;
    }

    public function render()
    {
        $task = $this->task();

        return view('livewire.outsourcer.task.submissions', [
            'submissions' => $task->submissions()->latest('version_no')->get(),
        ]);
    }

    private function task(): ProductCompletionTask
    {
        $task = ProductCompletionTask::query()->where('public_id', $this->taskPublicId)->firstOrFail();
        Gate::authorize('viewTask', $task);

        return $task;
    }
}
