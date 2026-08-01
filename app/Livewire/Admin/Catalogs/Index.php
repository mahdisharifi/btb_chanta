<?php

namespace App\Livewire\Admin\Catalogs;

use App\Models\CatalogTemplate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public function delete(int $id): void
    {
        $catalog = CatalogTemplate::findOrFail($id);
        $catalog->delete();

        session()->flash('message', 'کاتالوگ با موفقیت حذف شد.');

        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.admin.catalogs.index', [
            'catalogs' => CatalogTemplate::query()
                ->latest('id')
                ->paginate(15),
        ]);
    }
}
