<?php

declare(strict_types=1);

namespace App\Livewire\Outsourcer\Task;

use App\Models\ProductCompletionTask;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.outsourcing')]
class Show extends Component
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
        $task = ProductCompletionTask::query()
            ->where('public_id', $this->taskPublicId)
            ->with('product:id,title')
            ->firstOrFail();
        Gate::authorize('viewTask', $task);

        return view('livewire.outsourcer.task.show', ['task' => $task]);
    }
}
