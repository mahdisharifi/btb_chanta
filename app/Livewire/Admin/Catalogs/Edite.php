<?php
namespace App\Livewire\Admin\Catalogs;

use App\Models\CatalogTemplate;
use App\Models\Supplier;
use Livewire\Component;

class Edite extends Component
{
    public $name;
    public $is_global = false;
    public $selectedSupplierIds = []; // آرایه برای انتخاب چند تامین‌کننده

    public function save()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'is_global' => 'boolean',
            'selectedSupplierIds' => 'required_if:is_global,true|array',
        ]);

        CatalogTemplate::create([
            'name' => $this->name,
            'supplier_id' => $this->is_global ? null : auth()->id(), // یا logic دلخواه
            'config' => $this->is_global ? ['supplier_ids' => $this->selectedSupplierIds] : [],
        ]);

        session()->flash('message', 'کاتالوگ با موفقیت ایجاد شد.');
        return redirect()->route('admin.catalogs.index');
    }

    public function render()
    {
        return view('livewire.admin.catalog.create', [
            'suppliers' => Supplier::all() // برای نمایش در SelectBox
        ]);
    }
}
