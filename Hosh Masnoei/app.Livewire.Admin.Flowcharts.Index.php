<?php

namespace App\Livewire\Admin\Flowcharts;

use App\Models\Flowchart;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public $search = '';

    public function delete(Flowchart $flowchart)
    {
        $this->authorize('delete', $flowchart);
        $flowchart->delete();
        session()->flash('success', 'فلوچارت با موفقیت حذف شد.');
    }

    public function render()
    {
        $flowcharts = Flowchart::query()
            ->when($this->search, fn($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);

        return view('livewire.admin.flowcharts.index', compact('flowcharts'));
    }
}
