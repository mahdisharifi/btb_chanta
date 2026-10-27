<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Outsourcing;

use App\Enums\ProductCompletionCommissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionTask;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class Dashboard extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', ProductCompletionTask::class);
    }

    public function render()
    {
        $counts = ProductCompletionTask::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.admin.outsourcing.dashboard', [
            'counts' => $counts,
            'overdueCount' => ProductCompletionTask::query()
                ->whereNotIn('status', [
                    ProductCompletionTaskStatus::COMPLETED,
                    ProductCompletionTaskStatus::CANCELLED,
                    ProductCompletionTaskStatus::EXPIRED,
                ])
                ->where('due_at', '<', now())
                ->count(),
            'payableAmount' => ProductCompletionCommission::query()
                ->whereHas('task')
                ->where('status', ProductCompletionCommissionStatus::PAYABLE)
                ->sum('total_amount'),
            'paidAmount' => ProductCompletionCommission::query()
                ->whereHas('task')
                ->where('status', ProductCompletionCommissionStatus::PAID)
                ->sum('total_amount'),
        ]);
    }
}
