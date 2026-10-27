<?php

declare(strict_types=1);

namespace App\Livewire\Reviewer\Commissions;

use App\Enums\ProductCompletionCommissionStatus;
use App\Models\ProductCompletionCommission;
use App\Services\ProductCompletionCommissionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.outsourcing')]
class Index extends Component
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
    }

    public function adjust(string $publicId, ProductCompletionCommissionService $service): void
    {
        $commission = $this->commission($publicId);
        $service->adjust(
            auth()->user(),
            $commission,
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

    public function render()
    {
        Gate::authorize('outsourcing.view_reviewed_commissions');

        return view('livewire.reviewer.commissions.index', [
            'commissions' => ProductCompletionCommission::query()
                ->whereHas('task.lastSubmission', fn ($submission) => $submission->where('reviewed_by', auth()->id()))
                ->with([
                    'user:id,name',
                    'task:id,public_id,product_id,reviewer_id,last_submission_id,commission_rate_percentage',
                    'task.product:id,title',
                    'task.lastSubmission:id,reviewed_by',
                ])
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->latest('id')
                ->paginate(20),
            'statuses' => ProductCompletionCommissionStatus::options(),
        ]);
    }

    private function commission(string $publicId): ProductCompletionCommission
    {
        $commission = ProductCompletionCommission::query()
            ->where('public_id', $publicId)
            ->whereHas('task.lastSubmission', fn ($submission) => $submission->where('reviewed_by', auth()->id()))
            ->firstOrFail();
        Gate::authorize('viewCommission', $commission);

        return $commission;
    }
}
