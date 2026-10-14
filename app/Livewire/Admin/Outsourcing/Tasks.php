<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Outsourcing;

use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\ProductCompletionTask;
use App\Services\ProductCompletionTaskService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Tasks extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public ?int $productId = null;
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
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
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
        $this->commissionRuleId = null;
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
            dueAt: now()->addDays((int) $validated['dueDays']),
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
        $tasks = ProductCompletionTask::query()
            ->select([
                'id', 'public_id', 'product_id', 'assigned_to', 'task_type', 'status',
                'revision_count', 'revision_limit', 'due_at', 'created_at',
            ])
            ->with([
                'product:id,title',
                'assignee:id,name',
            ])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
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
                    $query->whereHas('product', function ($product) use ($term): void {
                        foreach (array_slice(preg_split('/\s+/u', $term), 0, 6) as $word) {
                            $pattern = '%'.strtr($word, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                            $product->where(fn ($part) => $part->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("sku LIKE ? ESCAPE '!'", [$pattern]));
                        }
                    });
                });
            })
            ->latest('id')
            ->paginate(20);

        return view('livewire.admin.outsourcing.tasks', [
            'tasks' => $tasks,
            'statuses' => ProductCompletionTaskStatus::options(),
            'commissionRules' => CommissionRule::query()
                ->where('is_active', true)
                ->select(['id', 'name', 'version', 'task_type'])
                ->latest('id')
                ->get(),
            'taskTypes' => ProductCompletionTaskType::options(),
        ]);
    }
}
