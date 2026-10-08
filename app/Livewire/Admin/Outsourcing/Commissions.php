<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Outsourcing;

use App\Enums\ProductCompletionCommissionStatus;
use App\Models\ProductCompletionCommission;
use App\Services\ProductCompletionCommissionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Commissions extends Component
{
    use WithPagination;

    public string $status = '';
    public array $transactionIds = [];
    public array $bonusAmounts = [];
    public array $penaltyAmounts = [];
    public array $adjustmentAmounts = [];
    public array $adjustmentReasons = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', ProductCompletionCommission::class);
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

    public function pay(string $publicId, ProductCompletionCommissionService $service): void
    {
        $transactionId = $this->transactionIds[$publicId] ?? '';
        $service->pay(auth()->user(), $this->commission($publicId), $transactionId, (string) Str::uuid());
        unset($this->transactionIds[$publicId]);
    }

    public function render()
    {
        Gate::authorize('outsourcing.view_all_commissions');

        return view('livewire.admin.outsourcing.commissions', [
            'commissions' => ProductCompletionCommission::query()
                ->with([
                    'user:id,name',
                    'task:id,public_id,product_id,reviewer_id,commission_rate_percentage',
                    'task.product:id,title',
                ])
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->latest('id')
                ->paginate(20),
            'statuses' => ProductCompletionCommissionStatus::options(),
        ]);
    }

    private function commission(string $publicId): ProductCompletionCommission
    {
        $commission = ProductCompletionCommission::query()->where('public_id', $publicId)->firstOrFail();
        Gate::authorize('viewCommission', $commission);

        return $commission;
    }
}
