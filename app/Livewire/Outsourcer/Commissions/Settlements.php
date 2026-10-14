<?php

declare(strict_types=1);

namespace App\Livewire\Outsourcer\Commissions;

use App\Models\OutsourcingSettlement;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.outsourcing')]
class Settlements extends Component
{
    use WithPagination;

    #[Locked]
    public ?string $selectedId = null;

    public function select(string $publicId): void
    {
        Gate::authorize('outsourcing.view_own_commissions');
        OutsourcingSettlement::query()->where('user_id', auth()->id())
            ->where('public_id', $publicId)->firstOrFail();
        $this->selectedId = $publicId;
        $this->resetPage('itemsPage');
    }

    public function render()
    {
        Gate::authorize('outsourcing.view_own_commissions');

        $selected = $this->selectedId === null ? null : OutsourcingSettlement::query()
            ->where('user_id', auth()->id())->where('public_id', $this->selectedId)
            ->with(['user:id,name', 'payments'])->firstOrFail();

        return view('livewire.outsourcer.commissions.settlements', [
            'settlements' => OutsourcingSettlement::query()->where('user_id', auth()->id())
                ->withCount('items')->latest('id')->paginate(15),
            'selected' => $selected,
            'statementItems' => $selected?->items()->orderBy('id')->paginate(50, ['*'], 'itemsPage'),
        ]);
    }
}
