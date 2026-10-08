<?php

declare(strict_types=1);

namespace App\Livewire\Outsourcer\Task;

use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use App\Models\ProductCompletionTask;
use App\Services\ProductCompletionTaskService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.outsourcing')]
class Index extends Component
{
    use WithPagination;

    public string $tab = 'mine';
    public string $status = '';
    public string $taskType = '';
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', ProductCompletionTask::class);
    }

    public function updatedTab(): void
    {
        if (! in_array($this->tab, ['mine', 'available'], true)
            || ($this->tab === 'available' && ! $this->canClaimAvailableTasks())) {
            $this->tab = 'mine';
        }

        $this->status = '';
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedTaskType(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function claim(string $publicId, ProductCompletionTaskService $service): void
    {
        $validated = validator(['publicId' => $publicId], [
            'publicId' => ['required', 'string', 'size:26', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
        ], [
            'publicId.regex' => 'شناسه تسک نامعتبر است.',
        ])->validate();

        $task = ProductCompletionTask::query()
            ->where('public_id', $validated['publicId'])
            ->firstOrFail();

        $service->claimAvailable(auth()->user(), $task);
        $this->dispatch('notify', message: 'تسک با موفقیت پذیرفته شد.', type: 'success');
        $this->tab = 'mine';
        $this->status = ProductCompletionTaskStatus::ACCEPTED->value;
        $this->resetPage();
    }

    public function render()
    {
        $canClaimAvailableTasks = $this->canClaimAvailableTasks();
        $isAvailableTab = $this->tab === 'available' && $canClaimAvailableTasks;
        $search = trim(mb_substr($this->search, 0, 100));

        $tasks = ProductCompletionTask::query()
            ->select([
                'id', 'public_id', 'product_id', 'assigned_to', 'task_type', 'status', 'revision_count',
                'revision_limit', 'due_at', 'accepted_at', 'started_at', 'created_at',
            ])
            ->with('product:id,title')
            ->when($isAvailableTab, function ($query): void {
                $query->where('status', ProductCompletionTaskStatus::AVAILABLE)
                    ->whereNull('assigned_to')
                    ->where(fn ($deadline) => $deadline->whereNull('due_at')->orWhere('due_at', '>', now()));
            }, fn ($query) => $query->where('assigned_to', auth()->id()))
            ->when(! $isAvailableTab && $this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->taskType !== '', fn ($query) => $query->where('task_type', $this->taskType))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($taskQuery) use ($search): void {
                    $taskQuery->where('public_id', 'like', '%'.$search.'%')
                        ->orWhereHas('product', fn ($product) => $product->where('title', 'like', '%'.$search.'%'));
                });
            })
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->latest('id')
            ->paginate(20);

        return view('livewire.outsourcer.task.index', [
            'tasks' => $tasks,
            'statuses' => ProductCompletionTaskStatus::options(),
            'taskTypes' => ProductCompletionTaskType::options(),
            'canClaimAvailableTasks' => $canClaimAvailableTasks,
        ]);
    }

    private function canClaimAvailableTasks(): bool
    {
        return auth()->user()?->can('outsourcing.accept_task') ?? false;
    }
}
