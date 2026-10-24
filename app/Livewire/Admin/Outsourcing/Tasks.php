<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Outsourcing;

use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use App\Models\CommissionRule;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionTaskService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Tasks extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';
    #[Url(except: '')]
    public string $status = '';
    #[Url(as: 'type', except: '')]
    public string $taskTypeFilter = '';
    #[Url(as: 'attention', except: '')]
    public string $attentionFilter = '';
    #[Url(as: 'assignee', except: '')]
    public string $assigneeFilter = '';
    #[Url(as: 'reviewer', except: '')]
    public string $reviewerFilter = '';
    #[Url(as: 'category', except: '')]
    public string $categoryFilter = '';
    #[Url(as: 'productId')]
    public ?int $productId = null;
    public bool $showCreateForm = false;
    public array $selectedTaskIds = [];
    public int $bulkDeadlineDays = 3;
    public ?int $commissionRuleId = null;
    public string $taskType = 'full_completion';
    public int $dueDays = 3;

    /** @var array<int, string> */
    public array $correctionScope = [];

    /** @var array<string, string> */
    public array $correctionScopeOptions = [
        'title' => 'عنوان محصول',
        'short_description' => 'توضیح کوتاه',
        'full_description' => 'توضیحات کامل',
        'attributes' => 'ویژگی‌ها',
        'gallery' => 'گالری تصاویر',
    ];

    public function mount(): void
    {
        Gate::authorize('viewAny', ProductCompletionTask::class);
        $this->showCreateForm = $this->productId !== null;
        $defaultRule = $this->defaultCommissionRule();
        if ($defaultRule !== null) {
            $this->taskType = $defaultRule->task_type->value;
            $this->commissionRuleId = (int) $defaultRule->getKey();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        if ($this->status !== '' && ! array_key_exists($this->status, ProductCompletionTaskStatus::options())) {
            $this->status = '';
        }

        $this->resetPage();
    }

    public function updatedTaskTypeFilter(): void
    {
        if ($this->taskTypeFilter !== '' && ! array_key_exists($this->taskTypeFilter, ProductCompletionTaskType::options())) {
            $this->taskTypeFilter = '';
        }

        $this->resetPage();
    }

    public function updatedAttentionFilter(): void
    {
        if (! in_array($this->attentionFilter, ['', 'needs_attention', 'overdue', 'finalization', 'review_queue'], true)) {
            $this->attentionFilter = '';
        }

        $this->resetPage();
    }

    public function updatedAssigneeFilter(): void
    {
        if ($this->assigneeFilter !== '' && ! ctype_digit($this->assigneeFilter)) {
            $this->assigneeFilter = '';
        }

        $this->resetPage();
    }

    public function updatedReviewerFilter(): void
    {
        if ($this->reviewerFilter !== '' && ! ctype_digit($this->reviewerFilter)) {
            $this->reviewerFilter = '';
        }

        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        if ($this->categoryFilter !== '' && ! ctype_digit($this->categoryFilter)) {
            $this->categoryFilter = '';
        }
        $this->resetPage();
    }

    public function updatedSelectedTaskIds(): void
    {
        $this->selectedTaskIds = array_values(array_unique(array_filter(
            array_map('intval', $this->selectedTaskIds),
            static fn (int $id): bool => $id > 0,
        )));
    }

    public function toggleCreateForm(): void
    {
        Gate::authorize('create', ProductCompletionTask::class);
        $this->showCreateForm = ! $this->showCreateForm;
    }

    public function setAttentionFilter(string $filter): void
    {
        if (! in_array($filter, ['needs_attention', 'overdue', 'finalization', 'review_queue'], true)) {
            throw ValidationException::withMessages(['attentionFilter' => 'فیلتر رسیدگی معتبر نیست.']);
        }

        $this->attentionFilter = $filter;
        $this->status = '';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'taskTypeFilter', 'attentionFilter', 'assigneeFilter', 'reviewerFilter', 'categoryFilter']);
        $this->resetPage();
    }

    public function bulkExtendDeadlines(ProductCompletionTaskService $service): void
    {
        Gate::authorize('outsourcing.extend_deadline');
        $this->validate(['bulkDeadlineDays' => ['required', 'integer', 'min:1', 'max:90']]);
        $ids = array_values(array_unique(array_map('intval', $this->selectedTaskIds)));
        if ($ids === []) {
            throw ValidationException::withMessages(['selectedTaskIds' => 'حداقل یک تسک را انتخاب کنید.']);
        }

        $updated = 0;
        $skipped = 0;
        $dueAt = now()->addDays($this->bulkDeadlineDays);
        foreach (ProductCompletionTask::query()->whereKey($ids)->get() as $task) {
            if (! Gate::allows('extendDeadline', $task)) {
                $skipped++;
                continue;
            }
            try {
                $service->extendDeadline(auth()->user(), $task, $dueAt);
                $updated++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        $this->selectedTaskIds = [];
        $message = "مهلت {$updated} تسک تمدید شد.";
        if ($skipped > 0) {
            $message .= " {$skipped} مورد به دلیل وضعیت یا خطا نادیده گرفته شد.";
        }
        $this->dispatch('notify', message: $message, type: $updated > 0 ? 'success' : 'warning');
    }

    #[Renderless]
    public function searchProducts(string $term): array
    {
        Gate::authorize('outsourcing.view_all_tasks');
        Gate::authorize('create', ProductCompletionTask::class);
        $term = mb_substr(preg_replace('/\s+/u', ' ', trim($term)), 0, 100);
        $term = strtr($term, array_combine(
            preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789'),
        ));
        if (mb_strlen($term) < 2 && ! ctype_digit($term)) {
            return [];
        }
        $escape = static fn (string $value): string => strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);
        $query = Product::query()->select(['id', 'title', 'sku'])
            ->withExists(['completionTasks as has_active_task' => fn ($task) => $task->whereNotNull('active_product_id')]);
        $present = static fn (Product $product): array => [
            'id' => $product->id, 'title' => $product->title, 'sku' => $product->sku,
            'has_active_task' => (bool) $product->has_active_task,
        ];
        if (ctype_digit($term) && strlen($term) <= 18) {
            $exact = (clone $query)->find((int) $term);
            if ($exact !== null) {
                return [$present($exact)];
            }
        }
        $query->where(function ($query) use ($term, $escape): void {
            if (ctype_digit($term) && strlen($term) <= 18) {
                $query->where('id', (int) $term)->orWhere(function ($query) use ($term, $escape): void {
                    $query->whereRaw("sku LIKE ? ESCAPE '!'", ['%'.$escape($term).'%'])
                        ->orWhereRaw("title LIKE ? ESCAPE '!'", ['%'.$escape($term).'%']);
                });
                return;
            }
            foreach (array_slice(explode(' ', $term), 0, 6) as $word) {
                $variants = array_unique([$word, strtr($word, ['ي' => 'ی', 'ك' => 'ک']), strtr($word, ['ی' => 'ي', 'ک' => 'ك'])]);
                $query->where(function ($query) use ($variants, $escape): void {
                    foreach ($variants as $variant) {
                        $query->orWhereRaw("title LIKE ? ESCAPE '!'", ['%'.$escape($variant).'%'])
                            ->orWhereRaw("sku LIKE ? ESCAPE '!'", ['%'.$escape($variant).'%']);
                    }
                });
            }
        });

        return $query->orderByRaw('CASE WHEN id = ? THEN 0 WHEN sku = ? THEN 1 WHEN title = ? THEN 2 ELSE 3 END', [ctype_digit($term) && strlen($term) <= 18 ? (int) $term : 0, $term, $term])
            ->orderByDesc('id')->limit(15)->get()->map($present)->all();
    }

    public function updatedTaskType(): void
    {
        $defaultRule = $this->defaultCommissionRule();
        $this->commissionRuleId = $defaultRule !== null && $defaultRule->task_type->value === $this->taskType
            ? (int) $defaultRule->getKey()
            : null;
        $this->correctionScope = [];
        $this->resetValidation(['commissionRuleId', 'correctionScope', 'scope', 'taskType']);
    }

    public function createTask(ProductCompletionTaskService $service): void
    {
        Gate::authorize('create', ProductCompletionTask::class);

        $validated = $this->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
            'commissionRuleId' => ['required', 'integer', 'exists:commission_rules,id'],
            'taskType' => ['required', Rule::enum(ProductCompletionTaskType::class)],
            'dueDays' => ['required', 'integer', 'min:1', 'max:90'],
            'correctionScope' => array_filter([
                'nullable',
                'array',
                Rule::requiredIf(fn () => $this->taskType === ProductCompletionTaskType::CORRECTION->value),
                $this->taskType === ProductCompletionTaskType::CORRECTION->value ? 'min:1' : null,
            ]),
            'correctionScope.*' => ['string', Rule::in(array_keys($this->correctionScopeOptions))],
        ], [
            'productId.required' => 'انتخاب محصول الزامی است.',
            'commissionRuleId.required' => 'انتخاب قانون پورسانت الزامی است.',
            'taskType.required' => 'انتخاب نوع تسک الزامی است.',
            'dueDays.min' => 'مهلت باید حداقل یک روز باشد.',
            'dueDays.max' => 'مهلت نمی‌تواند بیشتر از نود روز باشد.',
            'correctionScope.required' => 'برای تسک اصلاح محتوا حداقل یک بخش را انتخاب کنید.',
            'correctionScope.min' => 'برای تسک اصلاح محتوا حداقل یک بخش را انتخاب کنید.',
            'correctionScope.*.in' => 'بخش انتخاب‌شده برای اصلاح معتبر نیست.',
        ]);

        $taskType = ProductCompletionTaskType::from($validated['taskType']);
        $commissionRule = CommissionRule::query()->findOrFail((int) $validated['commissionRuleId']);

        if (! $commissionRule->is_active || $commissionRule->task_type !== $taskType) {
            throw ValidationException::withMessages([
                'commissionRuleId' => 'قانون پورسانت انتخاب‌شده برای نوع تسک فعلی فعال نیست.',
            ]);
        }

        $scope = $taskType === ProductCompletionTaskType::CORRECTION
            ? $this->buildCorrectionScope($validated['correctionScope'])
            : null;

        $service->create(
            actor: auth()->user(),
            product: Product::query()->findOrFail((int) $validated['productId']),
            commissionRule: $commissionRule,
            taskType: $taskType,
            scope: $scope,
            completionDays: (int) $validated['dueDays'],
            publishImmediately: true,
        );

        $this->reset(['productId', 'commissionRuleId', 'correctionScope']);
        $this->dispatch('outsourcing-product-cleared');
        $this->dispatch('notify', message: 'تسک ایجاد شد و برای پیمانکاران در فهرست تسک‌های آزاد منتشر شد.', type: 'success');
    }

    /**
     * @param array<int, string> $selectedFields
     * @return array<string, bool|array<string, bool>>
     */
    private function buildCorrectionScope(array $selectedFields): array
    {
        $selected = array_flip(array_values(array_unique($selectedFields)));
        $scope = [];

        foreach (array_keys($this->correctionScopeOptions) as $field) {
            if ($field === 'gallery') {
                $scope[$field] = isset($selected[$field])
                    ? [
                        'add' => true,
                        'reorder' => true,
                        'select_primary' => true,
                        'propose_removal' => true,
                    ]
                    : false;

                continue;
            }

            $scope[$field] = isset($selected[$field]);
        }

        return $scope;
    }

    public function render()
    {
        $search = mb_substr(trim($this->search), 0, 100);
        $categoryIds = [];
        if ($this->categoryFilter !== '') {
            $category = Category::query()->find((int) $this->categoryFilter);
            if ($category !== null) {
                $categoryIds = $category->getAllDescendantIdsRecursive()->all();
            }
        }

        $tasks = ProductCompletionTask::query()
            ->select([
                'id', 'public_id', 'product_id', 'active_product_id', 'assigned_by', 'assigned_to', 'reviewer_id',
                'bulk_creation_batch_id', 'task_type', 'status', 'revision_count', 'revision_limit', 'due_at',
                'completion_duration_seconds', 'assignment_expires_at', 'accepted_at', 'content_applied_at',
                'media_published_at', 'last_error', 'created_at',
            ])
            ->with([
                'product:id,title,sku',
                'assignee:id,name',
                'reviewer:id,name',
                'assigner:id,name',
            ])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->taskTypeFilter !== '', fn ($query) => $query->where('task_type', $this->taskTypeFilter))
            ->when($this->assigneeFilter !== '', fn ($query) => $query->where('assigned_to', (int) $this->assigneeFilter))
            ->when($this->reviewerFilter !== '', fn ($query) => $query->where('reviewer_id', (int) $this->reviewerFilter))
            ->when($categoryIds !== [], fn ($query) => $query->whereHas('product', fn ($product) => $product->whereIn('category_id', $categoryIds)))
            ->when($search !== '', function ($query) use ($search): void {
                $term = strtr($search, array_combine(
                    preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
                    str_split('01234567890123456789'),
                ));
                $query->where(function ($query) use ($term): void {
                    if (ctype_digit($term) && strlen($term) <= 18) {
                        $query->where('product_id', (int) $term)->orWhereHas('product', fn ($product) => $product->where('sku', $term));
                        return;
                    }
                    $query->where('public_id', $term)->orWhereHas('product', function ($product) use ($term): void {
                        foreach (array_slice(preg_split('/\s+/u', $term), 0, 6) as $word) {
                            $pattern = '%'.strtr($word, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                            $product->where(fn ($part) => $part->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("sku LIKE ? ESCAPE '!'", [$pattern]));
                        }
                    });
                });
            })
            ->when($this->attentionFilter !== '', fn (Builder $query) => $this->applyAttentionFilter($query, $this->attentionFilter))
            ->latest('id')
            ->paginate(20);

        $selectedProduct = $this->productId
            ? Product::query()->select(['id', 'title', 'sku'])->find($this->productId)?->only(['id', 'title', 'sku'])
            : null;
        $canCreateTasks = Gate::allows('create', ProductCompletionTask::class);
        $showCreateForm = $canCreateTasks && $this->showCreateForm;

        return view('livewire.admin.outsourcing.tasks', [
            'tasks' => $tasks,
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'selectedProduct' => $selectedProduct,
            'statuses' => ProductCompletionTaskStatus::options(),
            'commissionRules' => $showCreateForm ? CommissionRule::query()
                ->where('is_active', true)
                ->select(['id', 'name', 'version', 'task_type', 'is_default'])
                ->orderByDesc('is_default')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get() : collect(),
            'systemDefaultRuleId' => $this->defaultCommissionRule()?->getKey(),
            'taskTypes' => ProductCompletionTaskType::options(),
            'attentionOptions' => [
                'needs_attention' => 'نیازمند رسیدگی',
                'overdue' => 'مهلت گذشته',
                'finalization' => 'خطای نهایی‌سازی',
                'review_queue' => 'در انتظار بررسی',
            ],
            'assignees' => User::role('outsourcer')->orderBy('name')->get(['id', 'name']),
            'reviewers' => User::role('reviewer')->orderBy('name')->get(['id', 'name']),
            'taskMetrics' => $this->taskMetrics(),
            'canCreateTasks' => $canCreateTasks,
            'showCreateForm' => $showCreateForm,
        ]);
    }

    private function applyAttentionFilter(Builder $query, string $filter): Builder
    {
        $now = now();
        $activeStatuses = [
            ProductCompletionTaskStatus::DRAFT->value,
            ProductCompletionTaskStatus::AVAILABLE->value,
            ProductCompletionTaskStatus::ASSIGNED->value,
            ProductCompletionTaskStatus::ACCEPTED->value,
            ProductCompletionTaskStatus::IN_PROGRESS->value,
            ProductCompletionTaskStatus::SUBMITTED->value,
            ProductCompletionTaskStatus::CHANGES_REQUESTED->value,
            ProductCompletionTaskStatus::RESUBMITTED->value,
            ProductCompletionTaskStatus::APPROVED->value,
            ProductCompletionTaskStatus::CONFLICTED->value,
            ProductCompletionTaskStatus::DISPUTED->value,
        ];

        if ($filter === 'review_queue') {
            return $query->whereIn('status', [
                ProductCompletionTaskStatus::SUBMITTED->value,
                ProductCompletionTaskStatus::RESUBMITTED->value,
            ]);
        }

        if ($filter === 'finalization') {
            return $query->where(function (Builder $tasks): void {
                $tasks->where(fn (Builder $approved) => $approved
                    ->where('status', ProductCompletionTaskStatus::APPROVED->value)
                    ->where(fn (Builder $markers) => $markers->whereNull('content_applied_at')->orWhereNull('media_published_at')))
                    ->orWhere(fn (Builder $error) => $error->whereNotNull('last_error')->where('last_error', '<>', ''));
            });
        }

        $overdue = static fn (Builder $tasks) => $tasks->where(function (Builder $conditions) use ($now, $activeStatuses): void {
            $conditions->where(fn (Builder $due) => $due->whereIn('status', $activeStatuses)->whereNotNull('due_at')->where('due_at', '<', $now))
                ->orWhere(fn (Builder $assignment) => $assignment
                    ->where('status', ProductCompletionTaskStatus::ASSIGNED->value)
                    ->whereNotNull('assigned_to')
                    ->whereNull('accepted_at')
                    ->whereNotNull('assignment_expires_at')
                    ->where('assignment_expires_at', '<', $now));
        });

        if ($filter === 'overdue') {
            return $overdue($query);
        }

        return $query->where(function (Builder $tasks) use ($now, $activeStatuses, $overdue): void {
            $tasks->whereIn('status', [
                ProductCompletionTaskStatus::EXPIRED->value,
                ProductCompletionTaskStatus::CONFLICTED->value,
                ProductCompletionTaskStatus::DISPUTED->value,
            ])
                ->orWhere(fn (Builder $error) => $error->whereNotNull('last_error')->where('last_error', '<>', ''))
                ->orWhere($overdue)
                ->orWhere(fn (Builder $approved) => $approved
                    ->where('status', ProductCompletionTaskStatus::APPROVED->value)
                    ->where(fn (Builder $markers) => $markers->whereNull('content_applied_at')->orWhereNull('media_published_at')))
                ->orWhere(fn (Builder $missingActive) => $missingActive
                    ->whereIn('status', $activeStatuses)
                    ->whereNull('active_product_id'));
        });
    }

    private function taskMetrics(): object
    {
        $now = now();
        $activeStatuses = [
            ProductCompletionTaskStatus::DRAFT->value,
            ProductCompletionTaskStatus::AVAILABLE->value,
            ProductCompletionTaskStatus::ASSIGNED->value,
            ProductCompletionTaskStatus::ACCEPTED->value,
            ProductCompletionTaskStatus::IN_PROGRESS->value,
            ProductCompletionTaskStatus::SUBMITTED->value,
            ProductCompletionTaskStatus::CHANGES_REQUESTED->value,
            ProductCompletionTaskStatus::RESUBMITTED->value,
            ProductCompletionTaskStatus::APPROVED->value,
            ProductCompletionTaskStatus::CONFLICTED->value,
            ProductCompletionTaskStatus::DISPUTED->value,
        ];
        $attentionStatuses = [
            ProductCompletionTaskStatus::EXPIRED->value,
            ProductCompletionTaskStatus::CONFLICTED->value,
            ProductCompletionTaskStatus::DISPUTED->value,
        ];
        $activePlaceholders = implode(',', array_fill(0, count($activeStatuses), '?'));
        $attentionSql = 'SUM(CASE WHEN status IN (?, ?, ?)'
            .' OR (last_error IS NOT NULL AND last_error <> ?)'
            .' OR (due_at IS NOT NULL AND due_at < ? AND status IN ('.$activePlaceholders.'))'
            .' OR (status = ? AND assignment_expires_at IS NOT NULL AND assignment_expires_at < ? AND accepted_at IS NULL)'
            .' OR (status = ? AND (content_applied_at IS NULL OR media_published_at IS NULL))'
            .' OR (active_product_id IS NULL AND status IN ('.$activePlaceholders.'))'
            .' THEN 1 ELSE 0 END) AS attention_count';
        $attentionBindings = [
            ...$attentionStatuses,
            '',
            $now,
            ...$activeStatuses,
            ProductCompletionTaskStatus::ASSIGNED->value,
            $now,
            ProductCompletionTaskStatus::APPROVED->value,
            ...$activeStatuses,
        ];

        return ProductCompletionTask::query()
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS available_count', [ProductCompletionTaskStatus::AVAILABLE->value])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS review_count', [
                ProductCompletionTaskStatus::SUBMITTED->value,
                ProductCompletionTaskStatus::RESUBMITTED->value,
            ])
            ->selectRaw($attentionSql, $attentionBindings)
            ->first();
    }

    private function defaultCommissionRule(): ?CommissionRule
    {
        return CommissionRule::query()
            ->where('is_default', true)
            ->where('task_type', '!=', ProductCompletionTaskType::SEO->value)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->whereNull('effective_until')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }
}
