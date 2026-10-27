<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Outsourcing;

use App\Enums\ProductCompletionCommissionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionTask;
use App\Services\ProductCompletionCommissionService;
use App\Services\ProductCompletionFinalizationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Commissions extends Component
{
    use WithPagination;

    public string $status = '';
    public array $bonusAmounts = [];
    public array $penaltyAmounts = [];
    public array $adjustmentAmounts = [];
    public array $adjustmentReasons = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', ProductCompletionCommission::class);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
        $this->resetPage('approvedTasksPage');
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

    public function markPayable(string $publicId, ProductCompletionCommissionService $service): void
    {
        $service->markPayable(auth()->user(), $this->commission($publicId));
    }

    public function render()
    {
        Gate::authorize('outsourcing.view_all_commissions');

        return view('livewire.admin.outsourcing.commissions', [
            'commissions' => ProductCompletionCommission::query()
                ->whereHas('task')
                ->with([
                    'user:id,name',
                    'task:id,public_id,product_id,reviewer_id,commission_rate_percentage',
                    'task.product:id,title',
                ])
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->orderBy('calculated_at')
                ->orderBy('id')
                ->paginate(20),
            'approvedTasks' => ProductCompletionTask::query()
                ->with(['product:id,title', 'assignee:id,name', 'reviewer:id,name'])
                ->where('status', ProductCompletionTaskStatus::APPROVED)
                ->whereHas('lastSubmission', fn ($query) => $query->where('status', ProductCompletionSubmissionStatus::APPROVED))
                ->whereDoesntHave('commission')
                ->when(! in_array($this->status, ['', ProductCompletionCommissionStatus::PENDING->value], true), fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('approved_at')
                ->orderBy('id')
                ->paginate(20, ['*'], 'approvedTasksPage'),
            'statuses' => ProductCompletionCommissionStatus::options(),
        ]);
    }

    private function commission(string $publicId): ProductCompletionCommission
    {
        $commission = ProductCompletionCommission::query()->whereHas('task')->where('public_id', $publicId)->firstOrFail();
        Gate::authorize('viewCommission', $commission);

        return $commission;
    }
}
