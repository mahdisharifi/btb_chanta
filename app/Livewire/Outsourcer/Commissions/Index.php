<?php

declare(strict_types=1);

namespace App\Livewire\Outsourcer\Commissions;

use App\Models\ProductCompletionCommission;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.outsourcing')]
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', ProductCompletionCommission::class);
    }

    public function render()
    {
        return view('livewire.outsourcer.commissions.index', [
            'commissions' => ProductCompletionCommission::query()
                ->whereHas('task')
                ->where('user_id', auth()->id())
                ->with(['task:id,public_id,product_id', 'task.product:id,title'])
                ->latest('id')
                ->paginate(20),
        ]);
    }
}
