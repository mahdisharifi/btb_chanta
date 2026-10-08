<?php

declare(strict_types=1);

namespace App\Livewire\Outsourcer\Task;

use App\Models\Attribute;
use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionTask;
use App\Services\ProductCompletionDraftService;
use App\Services\ProductCompletionMediaService;
use App\Services\ProductCompletionSubmissionService;
use App\Services\ProductCompletionTaskService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.outsourcing')]
class Workspace extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $taskPublicId;

    public array $draft = [];
    public int $draftVersion = 0;
    public $upload = null;

    public function mount(ProductCompletionTask $task): void
    {
        Gate::authorize('viewTask', $task);
        $this->taskPublicId = $task->public_id;
        $this->syncDraft($task);
    }

    public function accept(ProductCompletionTaskService $service): void
    {
        $this->syncDraft($service->accept(auth()->user(), $this->task()));
    }

    public function start(ProductCompletionTaskService $service): void
    {
        $this->syncDraft($service->start(auth()->user(), $this->task()));
    }

    public function resumeRevision(ProductCompletionTaskService $service): void
    {
        $this->syncDraft($service->resumeRevision(auth()->user(), $this->task()));
    }

    public function saveDraft(ProductCompletionDraftService $service): void
    {
        $task = $service->save(auth()->user(), $this->task(), $this->draft, $this->draftVersion);
        $this->syncDraft($task);
        $this->dispatch('notify', message: 'پیش‌نویس ذخیره شد.', type: 'success');
    }

    public function submit(ProductCompletionSubmissionService $service): void
    {
        $submission = $service->submit(
            auth()->user(),
            $this->task(),
            $this->draftVersion,
            (string) Str::uuid(),
        );
        $this->syncDraft($submission->task()->firstOrFail());
        $this->dispatch('notify', message: 'نسخه برای بررسی ارسال شد.', type: 'success');
    }

    public function uploadMedia(
        ProductCompletionMediaService $mediaService,
        ProductCompletionDraftService $draftService,
    ): void {
        $this->validate([
            'upload' => ['required', 'image', 'max:'.(int) config('product_completion.media.max_file_size_kb', 2048)],
        ], [
            'upload.required' => 'انتخاب تصویر الزامی است.',
            'upload.image' => 'فایل انتخاب‌شده تصویر معتبر نیست.',
            'upload.max' => 'حجم تصویر بیشتر از حد مجاز است.',
        ]);
        $task = $this->task();
        $media = $mediaService->stage(auth()->user(), $task, $this->upload);
        $items = data_get($this->draft, 'gallery.items', []);
        $finalCount = count(array_filter($items, static fn (array $item): bool => ($item['action'] ?? '') !== 'remove'));
        $items[] = [
            'media_public_id' => $media->public_id,
            'product_gallery_id' => null,
            'action' => 'add',
            'sort_order' => $finalCount + 1,
            'is_primary' => $finalCount === 0,
            'is_seo_image' => false,
            'title' => $this->draft['title'] ?? null,
            'alt_text' => $this->draft['title'] ?? null,
        ];
        data_set($this->draft, 'gallery.items', $items);
        $task = $draftService->save(auth()->user(), $task, $this->draft, $this->draftVersion);
        $this->upload = null;
        $this->syncDraft($task);
    }

    public function selectPrimary(int $index): void
    {
        foreach (data_get($this->draft, 'gallery.items', []) as $itemIndex => $item) {
            data_set($this->draft, "gallery.items.{$itemIndex}.is_primary", $itemIndex === $index && ($item['action'] ?? '') !== 'remove');
        }
    }

    public function selectSeo(int $index): void
    {
        foreach (data_get($this->draft, 'gallery.items', []) as $itemIndex => $item) {
            data_set($this->draft, "gallery.items.{$itemIndex}.is_seo_image", $itemIndex === $index && ($item['action'] ?? '') !== 'remove');
        }
    }

    public function toggleAttribute(int $attributeId, int $valueId, bool $multiple): void
    {
        $attributes = collect($this->draft['attributes'] ?? [])->keyBy('attribute_id');
        $selection = $attributes->get($attributeId, ['attribute_id' => $attributeId, 'value_ids' => []]);
        $values = array_map('intval', $selection['value_ids'] ?? []);
        $selection['value_ids'] = $multiple
            ? (in_array($valueId, $values, true)
                ? array_values(array_diff($values, [$valueId]))
                : array_values(array_unique([...$values, $valueId])))
            : [$valueId];
        $attributes->put($attributeId, $selection);
        $this->draft['attributes'] = $attributes->values()->all();
    }

    public function render()
    {
        $task = $this->task();
        $categoryId = (int) data_get($task->initial_snapshot_json, 'category_id');
        $attributes = data_get($task->scope_json, 'attributes', false)
            ? Attribute::query()
                ->whereHas('categories', fn ($query) => $query->whereKey($categoryId))
                ->with('values:id,attribute_id,value')
                ->select(['id', 'name', 'is_multiple'])
                ->get()
            : collect();
        $media = ProductCompletionMedia::query()
            ->where('task_id', $task->getKey())
            ->select(['id', 'public_id', 'task_id', 'original_name', 'status', 'width', 'height'])
            ->get()
            ->keyBy('public_id');

        return view('livewire.outsourcer.task.workspace', compact('task', 'attributes', 'media'));
    }

    private function task(): ProductCompletionTask
    {
        $task = ProductCompletionTask::query()->where('public_id', $this->taskPublicId)->firstOrFail();
        Gate::authorize('viewTask', $task);

        return $task;
    }

    private function syncDraft(ProductCompletionTask $task): void
    {
        $task->refresh();
        $this->draft = $task->current_draft_json ?? [];
        $this->draftVersion = $task->draft_version;
    }
}
