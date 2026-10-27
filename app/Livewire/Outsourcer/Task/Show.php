<?php

declare(strict_types=1);

namespace App\Livewire\Outsourcer\Task;

use App\Models\ProductCompletionTask;
use App\Models\Category;
use App\Services\ProductCompletionTaskService;
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
        $this->authorizeTask($task);
        $this->taskPublicId = $task->public_id;
    }

    public function claim(ProductCompletionTaskService $service)
    {
        $task = $this->task();
        Gate::authorize('previewAvailableTask', $task);
        $service->claimAvailable(auth()->user(), $task);

        return $this->redirectRoute('outsourcer.tasks.workspace', ['task' => $task], navigate: true);
    }

    public function render()
    {
        $task = $this->task()->load('product:id,title,category_id');
        $isPreview = $task->status === \App\Enums\ProductCompletionTaskStatus::AVAILABLE && $task->assigned_to === null;
        $canClaim = Gate::allows('previewAvailableTask', $task);
        $categoryRows = Category::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $categorySegments = [];
        $categoryId = $task->product?->category_id;
        $visitedCategories = [];
        while ($categoryId !== null && isset($categoryRows[$categoryId]) && ! isset($visitedCategories[$categoryId])) {
            $visitedCategories[$categoryId] = true;
            array_unshift($categorySegments, $categoryRows[$categoryId]->name);
            $categoryId = $categoryRows[$categoryId]->parent_id;
        }
        $categoryPath = implode(' ← ', $categorySegments);
        $canViewProductReference = $isPreview
            || $task->assigned_to === auth()->id()
            || Gate::allows('viewProductDraft', $task);
        $images = collect();
        $attributeValues = collect();
        if ($canViewProductReference) {
            $task->load([
                'product:id,title,category_id,sku,short_description,full_description',
                'product.category:id,name,parent_id',
                'product.attributeValues.attribute:id,name',
            ]);
            $images = $task->product?->galleries()
                ->where('file_type', 'image')
                ->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'product_id', 'alt_text', 'is_primary']) ?? collect();
            $attributeValues = $task->product?->attributeValues?->groupBy('attribute_id') ?? collect();
        }
        $backUrl = route('outsourcer.tasks.index', collect(request()->query())->only(['tab', 'status', 'taskType', 'search', 'category'])->all());
        $availabilityDeadlineLabel = $task->due_at
            ? \Morilog\Jalali\CalendarUtils::convertNumbers(jdate($task->due_at->copy()->timezone(config('app.timezone')))->format('Y/m/d H:i'))
            : 'بدون مهلت پذیرش';
        $completionDurationLabel = null;
        if ($task->completion_duration_seconds !== null) {
            $durationMinutes = (int) ceil($task->completion_duration_seconds / 60);
            $completionDurationLabel = $durationMinutes % 1440 === 0
                ? (int) ($durationMinutes / 1440).' روز'
                : ($durationMinutes % 60 === 0 ? (int) ($durationMinutes / 60).' ساعت' : $durationMinutes.' دقیقه');
            $completionDurationLabel = \Morilog\Jalali\CalendarUtils::convertNumbers($completionDurationLabel);
        }

        return view('livewire.outsourcer.task.show', compact('task', 'images', 'attributeValues', 'isPreview', 'canClaim', 'categoryPath', 'canViewProductReference', 'backUrl', 'availabilityDeadlineLabel', 'completionDurationLabel'));
    }

    private function task(): ProductCompletionTask
    {
        $task = ProductCompletionTask::query()->where('public_id', $this->taskPublicId)->firstOrFail();
        $this->authorizeTask($task);

        return $task;
    }

    private function authorizeTask(ProductCompletionTask $task): void
    {
        if (Gate::allows('viewTask', $task)) {
            return;
        }

        Gate::authorize('previewAvailableTask', $task);
    }
}
