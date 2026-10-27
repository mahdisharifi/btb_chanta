<?php

declare(strict_types=1);

namespace App\Livewire\Outsourcer\Task;

use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use App\Models\Category;
use App\Models\ProductCompletionTask;
use App\Services\ProductCompletionTaskService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.outsourcing')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'mine';
    #[Url]
    public string $status = '';
    #[Url]
    public string $taskType = '';
    #[Url]
    public string $search = '';
    #[Url(as: 'category')]
    public string $categoryId = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', ProductCompletionTask::class);
        $this->validateCategoryFilter();
        if (! in_array($this->tab, ['mine', 'available'], true)
            || ($this->tab === 'available' && ! $this->canClaimAvailableTasks())) {
            $this->tab = 'mine';
        }
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

    public function updatedCategoryId(): void
    {
        $this->validateCategoryFilter();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->categoryId = '';
        $this->taskType = '';
        $this->search = '';
        $this->status = '';
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
        $this->validateCategoryFilter();

        $categories = Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'parent_id', 'name']);
        $categoryRows = $categories->keyBy('id');
        $childrenByParent = [];
        foreach ($categories as $item) {
            $childrenByParent[$item->parent_id ?? 0][] = $item->id;
        }
        $categoryIds = null;
        if ($this->categoryId !== '') {
            $categoryIds = collect();
            $visitedCategories = [];
            $pending = [(int) $this->categoryId];
            while ($pending !== []) {
                $current = array_pop($pending);
                if (isset($visitedCategories[$current]) || ! isset($categoryRows[$current])) {
                    continue;
                }
                $visitedCategories[$current] = true;
                $categoryIds->push($current);
                array_push($pending, ...($childrenByParent[$current] ?? []));
            }
        }
        $categoryPaths = [];
        foreach ($categories as $item) {
            $segments = [$item->name];
            $parentId = $item->parent_id;
            $visited = [$item->id => true];
            while ($parentId !== null && isset($categoryRows[$parentId]) && ! isset($visited[$parentId])) {
                $visited[$parentId] = true;
                array_unshift($segments, $categoryRows[$parentId]->name);
                $parentId = $categoryRows[$parentId]->parent_id;
            }
            $categoryPaths[$item->id] = implode(' ← ', $segments);
        }

        $tasks = ProductCompletionTask::query()
            ->select([
                'id', 'public_id', 'product_id', 'assigned_to', 'task_type', 'status', 'revision_count',
                'revision_limit', 'due_at', 'completion_duration_seconds', 'accepted_at', 'started_at', 'created_at',
            ])
            ->with(['product:id,title,category_id', 'product.category:id,name'])
            ->when($isAvailableTab, function ($query): void {
                $query->where('status', ProductCompletionTaskStatus::AVAILABLE)
                    ->whereNull('assigned_to')
                    ->where(fn ($deadline) => $deadline->whereNull('due_at')->orWhere('due_at', '>', now()));
            }, fn ($query) => $query->where('assigned_to', auth()->id()))
            ->when(! $isAvailableTab && $this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->taskType !== '', fn ($query) => $query->where('task_type', $this->taskType))
            ->when($categoryIds !== null, fn ($query) => $query->whereHas('product', fn ($product) => $product->whereIn('category_id', $categoryIds)))
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
            'categories' => $categories,
            'categoryPaths' => $categoryPaths,
            'canClaimAvailableTasks' => $canClaimAvailableTasks,
        ]);
    }

    private function canClaimAvailableTasks(): bool
    {
        return auth()->user()?->can('outsourcing.accept_task') ?? false;
    }

    private function validateCategoryFilter(): void
    {
        if ($this->categoryId === '') {
            return;
        }

        validator(['categoryId' => $this->categoryId], [
            'categoryId' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')->where('is_active', true)],
        ])->validate();
    }
}
