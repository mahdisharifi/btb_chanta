<?php

declare(strict_types=1);

namespace App\Livewire\Supplier;

use App\Models\ProductCompletionTask;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.supplier')]
class ProductCompletionRequests extends Component
{
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', ProductCompletionTask::class);
    }

    public function render()
    {
        $supplierId = auth()->user()->supplier()->value('id');

        return view('livewire.supplier.product-completion-requests', [
            'tasks' => ProductCompletionTask::query()
                ->where('supplier_id', $supplierId)
                ->select([
                    'id', 'public_id', 'product_id', 'assigned_to', 'status',
                    'revision_count', 'revision_limit', 'due_at', 'created_at',
                ])
                ->with(['product:id,title', 'assignee:id,name'])
                ->latest('id')
                ->paginate(20),
        ]);
    }
}
