<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Outsourcing;

use App\Enums\ProductCompletionCommissionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use App\Models\CommissionRule;
use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionCommissionRuleChangeService;
use App\Services\ProductCompletionCommissionService;
use App\Services\ProductCompletionFinalizationService;
use App\Support\SettlementDisplay;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Commissions extends Component
{
    use WithPagination;

    public string $status = '';

    public string $filterUserId = '';

    public string $filterTaskType = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $search = '';

    public string $settlementFilter = '';

    public array $selectedCommissions = [];

    public int $perPage = 20;

    #[Locked]
    public bool $allPagesSelected = false;

    public array $bonusAmounts = [];

    public array $penaltyAmounts = [];

    public array $adjustmentAmounts = [];

    public array $adjustmentReasons = [];

    public array $ruleIds = [];

    public array $ruleReasons = [];

    public array $rulePreviews = [];

    public string $bulkUserId = '';

    public string $bulkTaskType = 'full_completion';

    public string $bulkSourceRuleId = '';

    public string $bulkRuleId = '';

    public string $bulkReason = '';

    public array $bulkSelected = [];

    #[Locked]
    public array $bulkPreview = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', ProductCompletionCommission::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'filterUserId', 'filterTaskType', 'dateFrom', 'dateTo', 'search', 'settlementFilter'], true)) {
            $this->clearCommissionSelection();
            $this->resetPage();
            $this->resetPage('approvedTasksPage');
        }
        if ($property === 'perPage') {
            $this->validate(['perPage' => ['required', 'integer', Rule::in([10, 20, 50, 100])]]);
            $this->resetPage();
            $this->resetPage('approvedTasksPage');
        }
        if (in_array($property, ['bulkUserId', 'bulkTaskType', 'bulkSourceRuleId', 'bulkRuleId'], true)) {
            $this->bulkPreview = [];
            $this->bulkSelected = [];
            if ($property === 'bulkTaskType') {
                $this->bulkSourceRuleId = '';
                $this->bulkRuleId = '';
            }
        }
    }

    public function previewBulkRule(ProductCompletionCommissionRuleChangeService $service): void
    {
        Gate::authorize('changeRules', ProductCompletionCommission::class);
        $this->bulkPreview = [];
        $this->bulkSelected = [];
        $this->validate([
            'bulkUserId' => ['required', 'integer', 'exists:users,id'],
            'bulkTaskType' => ['required', Rule::in(array_keys(ProductCompletionTaskType::options()))],
            'bulkSourceRuleId' => ['nullable', 'integer', Rule::exists('commission_rules', 'id')->where('task_type', $this->bulkTaskType)],
            'bulkRuleId' => ['required', 'integer', Rule::exists('commission_rules', 'id')->where('task_type', $this->bulkTaskType)],
        ], [], ['bulkUserId' => 'پیمانکار', 'bulkTaskType' => 'نوع تسک', 'bulkSourceRuleId' => 'قانون فعلی', 'bulkRuleId' => 'قانون جدید']);

        $this->bulkPreview = $service->previewMany(auth()->user(), (int) $this->bulkUserId,
            CommissionRule::query()->findOrFail((int) $this->bulkRuleId),
            $this->bulkSourceRuleId !== '' ? (int) $this->bulkSourceRuleId : null);
        $this->bulkSelected = array_keys($this->bulkPreview['rows']);
    }

    public function selectAllBulk(): void
    {
        Gate::authorize('changeRules', ProductCompletionCommission::class);
        $this->bulkSelected = array_keys($this->bulkPreview['rows'] ?? []);
    }

    public function clearBulkSelection(): void
    {
        $this->bulkSelected = [];
    }

    public function changeBulkRule(ProductCompletionCommissionRuleChangeService $service): void
    {
        Gate::authorize('changeRules', ProductCompletionCommission::class);
        $this->validate([
            'bulkReason' => ['required', 'string', 'max:2000'],
            'bulkSelected' => ['required', 'array', 'min:1'],
            'bulkSelected.*' => ['required', 'string', 'distinct'],
        ], [], ['bulkReason' => 'دلیل تغییر', 'bulkSelected' => 'تسک‌های منتخب']);
        $preview = $this->bulkPreview;
        if ((int) ($preview['user_id'] ?? 0) !== (int) $this->bulkUserId
            || (int) ($preview['rule_id'] ?? 0) !== (int) $this->bulkRuleId
            || (string) ($preview['task_type'] ?? '') !== $this->bulkTaskType
            || (string) ($preview['source_rule_id'] ?? '') !== $this->bulkSourceRuleId) {
            throw ValidationException::withMessages(['bulk' => 'ابتدا پیش‌نمایش را با فیلترهای فعلی محاسبه کنید.']);
        }
        $tokens = [];
        foreach ($this->bulkSelected as $publicId) {
            if (! isset($preview['rows'][$publicId])) {
                throw ValidationException::withMessages(['bulk' => 'یکی از موارد انتخاب‌شده در پیش‌نمایش وجود ندارد.']);
            }
            $tokens[$publicId] = $preview['rows'][$publicId]['token'];
        }
        $count = $service->changeMany(auth()->user(), (int) $this->bulkUserId,
            CommissionRule::query()->findOrFail((int) $this->bulkRuleId), $tokens, $this->bulkReason);
        $this->bulkPreview = [];
        $this->bulkSelected = [];
        $this->bulkReason = '';
        $this->dispatch('notify', message: "قانون {$count} تسک تغییر کرد و پورسانت آن‌ها دوباره محاسبه شد.", type: 'success');
    }

    private function bulkSummary(): array
    {
        $oldTotal = BigDecimal::zero();
        $newTotal = BigDecimal::zero();
        $rows = array_intersect_key($this->bulkPreview['rows'] ?? [], array_flip($this->bulkSelected));
        foreach ($rows as $row) {
            $oldTotal = $oldTotal->plus($row['old_total']);
            $newTotal = $newTotal->plus($row['new_total']);
        }

        return ['count' => count($rows), 'old_total' => (string) $oldTotal->toScale(2),
            'new_total' => (string) $newTotal->toScale(2), 'difference' => (string) $newTotal->minus($oldTotal)->toScale(2)];
    }

    public function calculate(string $publicId, ProductCompletionFinalizationService $service): void
    {
        Gate::authorize('outsourcing.view_all_commissions');
        Gate::authorize('outsourcing.approve_commission');
        $task = ProductCompletionTask::query()->where('public_id', $publicId)->firstOrFail();

        if ($task->status !== ProductCompletionTaskStatus::APPROVED
            || $task->lastSubmission?->status !== ProductCompletionSubmissionStatus::APPROVED) {
            throw ValidationException::withMessages([
                'task' => 'فقط تسک تأییدشده بازبین قابل آماده‌سازی برای بررسی مالی است.',
            ]);
        }

        $service->finalize($task);
        $this->dispatch('notify', message: 'پورسانت محاسبه شد و آماده بررسی مالی است.', type: 'success');
    }

    public function adjust(string $publicId, ProductCompletionCommissionService $service): void
    {
        $service->adjust(
            auth()->user(),
            $this->commission($publicId),
            (string) ($this->bonusAmounts[$publicId] ?? '0'),
            (string) ($this->penaltyAmounts[$publicId] ?? '0'),
            (string) ($this->adjustmentAmounts[$publicId] ?? '0'),
            (string) ($this->adjustmentReasons[$publicId] ?? ''),
        );
        unset(
            $this->bonusAmounts[$publicId],
            $this->penaltyAmounts[$publicId],
            $this->adjustmentAmounts[$publicId],
            $this->adjustmentReasons[$publicId],
        );
        $this->dispatch('notify', message: 'تعدیل مبلغ با موفقیت ثبت شد.', type: 'success');
    }

    public function approve(string $publicId, ProductCompletionCommissionService $service): void
    {
        $service->approve(auth()->user(), $this->commission($publicId));
    }

    public function previewRule(string $publicId, ProductCompletionCommissionRuleChangeService $service): void
    {
        $rule = CommissionRule::query()->findOrFail((int) ($this->ruleIds[$publicId] ?? 0));
        $preview = $service->preview(auth()->user(), $this->commission($publicId), $rule);
        $this->rulePreviews[$publicId] = [
            'rule_id' => $rule->getKey(), 'old_total' => $preview['old_total'],
            'new_total' => $preview['result']['total_amount'], 'token' => $preview['token'],
        ];
    }

    public function changeRule(string $publicId, ProductCompletionCommissionRuleChangeService $service): void
    {
        $preview = $this->rulePreviews[$publicId] ?? [];
        $ruleId = (int) ($this->ruleIds[$publicId] ?? 0);
        if ($ruleId === 0 || $ruleId !== (int) ($preview['rule_id'] ?? 0)) {
            throw ValidationException::withMessages(['rule' => 'ابتدا پیش‌نمایش قانون انتخاب‌شده را محاسبه کنید.']);
        }
        $service->change(auth()->user(), $this->commission($publicId), CommissionRule::query()->findOrFail($ruleId),
            (string) ($this->ruleReasons[$publicId] ?? ''), (string) ($preview['token'] ?? ''));
        unset($this->ruleIds[$publicId], $this->ruleReasons[$publicId], $this->rulePreviews[$publicId]);
        $this->dispatch('notify', message: 'قانون تغییر کرد و پورسانت دوباره محاسبه شد.', type: 'success');
    }

    public function markPayable(string $publicId, ProductCompletionCommissionService $service): void
    {
        $service->markPayable(auth()->user(), $this->commission($publicId));
    }

    public function selectPage(string $action): void
    {
        Gate::authorize('outsourcing.view_all_commissions');
        Gate::authorize('outsourcing.approve_commission');
        abort_unless(in_array($action, ['approve', 'payable'], true), 404);
        $ability = $action === 'approve' ? 'approveCommission' : 'markPayable';
        $this->allPagesSelected = false;
        $this->selectedCommissions = $this->commissionQuery()->paginate($this->pageSize())->getCollection()
            ->filter(fn ($commission) => Gate::allows($ability, $commission))
            ->pluck('public_id')->all();
    }

    public function selectAllPages(string $action): void
    {
        Gate::authorize('outsourcing.view_all_commissions');
        Gate::authorize('outsourcing.approve_commission');
        abort_unless(in_array($action, ['approve', 'payable'], true), 404);

        $statuses = $action === 'approve'
            ? [ProductCompletionCommissionStatus::CALCULATED, ProductCompletionCommissionStatus::UNDER_REVIEW]
            : [ProductCompletionCommissionStatus::APPROVED];

        $this->selectedCommissions = $this->commissionQuery()
            ->whereNull('settlement_id')->whereIn('status', $statuses)
            ->pluck('public_id')->all();
        $this->allPagesSelected = true;
    }

    public function clearCommissionSelection(): void
    {
        $this->selectedCommissions = [];
        $this->allPagesSelected = false;
    }

    public function resetFilters(): void
    {
        $this->reset('status', 'filterUserId', 'filterTaskType', 'dateFrom', 'dateTo', 'search', 'settlementFilter');
        $this->clearCommissionSelection();
        $this->resetPage();
        $this->resetPage('approvedTasksPage');
    }

    public function updatingPaginators(): void
    {
        if (! $this->allPagesSelected) {
            $this->selectedCommissions = [];
        }
    }

    public function processSelected(string $action, ProductCompletionCommissionService $service): void
    {
        Gate::authorize('outsourcing.view_all_commissions');
        Gate::authorize('outsourcing.approve_commission');
        $this->validate([
            'selectedCommissions' => ['required', 'array', 'min:1'],
            'selectedCommissions.*' => ['required', 'string', 'distinct'],
        ]);
        if (! in_array($action, ['approve', 'payable'], true)) {
            abort(404);
        }

        $ids = $this->selectedCommissions;
        DB::transaction(function () use ($ids, $action, $service): void {
            $rows = ProductCompletionCommission::query()->whereHas('task')
                ->whereIn('public_id', $ids)->orderBy('id')->lockForUpdate()->get();
            $ability = $action === 'approve' ? 'approveCommission' : 'markPayable';
            if ($rows->count() !== count($ids) || $rows->contains(fn ($row) => Gate::denies($ability, $row))) {
                throw ValidationException::withMessages(['selectedCommissions' => 'وضعیت یا دسترسی یک یا چند پورسانت تغییر کرده است. فهرست را بررسی و دوباره انتخاب کنید.']);
            }
            foreach ($rows as $row) {
                if ($action === 'approve') {
                    $service->approve(auth()->user(), $row);
                } else {
                    $service->markPayable(auth()->user(), $row);
                }
            }
        }, 3);
        $count = count($ids);
        $this->clearCommissionSelection();
        $this->dispatch('notify', message: "عملیات برای {$count} پورسانت انجام شد.", type: 'success');
    }

    private function pageSize(): int
    {
        return in_array($this->perPage, [10, 20, 50, 100], true) ? $this->perPage : 20;
    }

    private function commissionQuery(): Builder
    {
        $dates = $this->dateBounds();

        return ProductCompletionCommission::query()->whereHas('task')
            ->with(['user:id,name', 'task:id,public_id,product_id,reviewer_id,commission_rate_percentage,task_type,commission_rule_id', 'task.product:id,title', 'commissionRule:id,name,version'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->filterUserId !== '', fn ($query) => $query->where('user_id', $this->filterUserId))
            ->when($this->filterTaskType !== '', fn ($query) => $query->whereHas('task', fn ($task) => $task->where('task_type', $this->filterTaskType)))
            ->when($dates === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($dates['from'] ?? null, fn ($query, $from) => $query->where('calculated_at', '>=', $from))
            ->when($dates['until'] ?? null, fn ($query, $until) => $query->where('calculated_at', '<', $until))
            ->when($this->settlementFilter === 'unreserved', fn ($query) => $query->whereNull('settlement_id'))
            ->when($this->settlementFilter === 'reserved', fn ($query) => $query->whereNotNull('settlement_id'))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('public_id', 'like', '%'.trim($this->search).'%')
                ->orWhereHas('task', fn ($task) => $task->where('public_id', 'like', '%'.trim($this->search).'%')
                    ->orWhereHas('product', fn ($product) => $product->where('title', 'like', '%'.trim($this->search).'%')))))
            ->orderBy('calculated_at')->orderBy('id');
    }

    private function dateBounds(): ?array
    {
        $this->resetValidation(['dateFrom', 'dateTo']);
        $dates = [];
        foreach (['dateFrom', 'dateTo'] as $field) {
            if (trim($this->{$field}) === '') {
                $dates[$field] = null;

                continue;
            }
            try {
                $dates[$field] = SettlementDisplay::gregorian($this->{$field}, $field);
            } catch (ValidationException $exception) {
                $this->addError($field, $exception->errors()[$field][0]);
            }
        }
        if (count($dates) !== 2) {
            return null;
        }
        if ($dates['dateFrom'] !== null && $dates['dateTo'] !== null && $dates['dateFrom'] > $dates['dateTo']) {
            $this->addError('dateTo', 'تاریخ پایان باید برابر یا بعد از تاریخ شروع باشد.');

            return null;
        }

        return [
            'from' => $dates['dateFrom'] === null ? null : CarbonImmutable::parse($dates['dateFrom'], 'Asia/Tehran')
                ->startOfDay()->setTimezone(config('app.timezone')),
            'until' => $dates['dateTo'] === null ? null : CarbonImmutable::parse($dates['dateTo'], 'Asia/Tehran')
                ->startOfDay()->addDay()->setTimezone(config('app.timezone')),
        ];
    }

    public function render()
    {
        Gate::authorize('outsourcing.view_all_commissions');

        $selectedRows = ProductCompletionCommission::query()->whereHas('task')->whereIn('public_id', $this->selectedCommissions)->get();
        $selection = [
            'count' => $selectedRows->count(),
            'approvable' => $selectedRows->filter(fn ($row) => Gate::allows('approveCommission', $row))->count(),
            'payable' => $selectedRows->filter(fn ($row) => Gate::allows('markPayable', $row))->count(),
            'totals' => $selectedRows->groupBy('currency')->map(fn ($rows) => (string) $rows->reduce(
                fn (BigDecimal $total, $row) => $total->plus($row->total_amount), BigDecimal::zero())),
        ];

        return view('livewire.admin.outsourcing.commissions', [
            'selection' => $selection,
            'commissions' => $this->commissionQuery()->paginate($this->pageSize()),
            'filterContractors' => User::query()->whereIn('id', ProductCompletionCommission::query()->select('user_id')->whereHas('task'))->orderBy('name')->get(['id', 'name']),
            'approvedTasks' => ProductCompletionTask::query()
                ->with(['product:id,title', 'assignee:id,name', 'reviewer:id,name'])
                ->where('status', ProductCompletionTaskStatus::APPROVED)
                ->whereHas('lastSubmission', fn ($query) => $query->where('status', ProductCompletionSubmissionStatus::APPROVED))
                ->whereDoesntHave('commission')
                ->when($this->filterUserId !== '', fn ($query) => $query->where('assigned_to', $this->filterUserId))
                ->when($this->filterTaskType !== '', fn ($query) => $query->where('task_type', $this->filterTaskType))
                ->when($this->dateFrom !== '' || $this->dateTo !== '', fn ($query) => $query->whereRaw('1 = 0'))
                ->when($this->settlementFilter !== '', fn ($query) => $query->whereRaw('1 = 0'))
                ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($search) => $search
                    ->where('public_id', 'like', '%'.trim($this->search).'%')
                    ->orWhereHas('product', fn ($product) => $product->where('title', 'like', '%'.trim($this->search).'%'))))
                ->when(! in_array($this->status, ['', ProductCompletionCommissionStatus::PENDING->value], true), fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('approved_at')
                ->orderBy('id')
                ->paginate($this->pageSize(), ['*'], 'approvedTasksPage'),
            'statuses' => ProductCompletionCommissionStatus::options(),
            'canChangeRules' => Gate::allows('changeRules', ProductCompletionCommission::class),
            'contractors' => Gate::allows('changeRules', ProductCompletionCommission::class)
                ? User::query()->whereIn('id', ProductCompletionCommission::query()->select('user_id')->whereHas('task'))->orderBy('name')->get(['id', 'name'])
                : collect(),
            'taskTypes' => ProductCompletionTaskType::options(),
            'sourceRules' => Gate::allows('changeRules', ProductCompletionCommission::class)
                ? CommissionRule::query()->where('task_type', $this->bulkTaskType)->orderBy('name')->orderBy('version')->get(['id', 'name', 'version'])
                : collect(),
            'bulkSummary' => $this->bulkSummary(),
            'rules' => CommissionRule::query()->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', now()))
                ->orderBy('name')->get(),
        ]);
    }

    private function commission(string $publicId): ProductCompletionCommission
    {
        $commission = ProductCompletionCommission::query()->whereHas('task')->where('public_id', $publicId)->firstOrFail();
        Gate::authorize('viewCommission', $commission);

        return $commission;
    }
}
