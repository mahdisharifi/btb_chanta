<?php

namespace App\Livewire\Supplier;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

class MyProducts extends Component
{
    use WithPagination;

    public $search = '';

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function deleteProduct($productId)
    {
        $supplier = Auth::user()->supplier;

        if ($supplier) {
            $supplier->products()->detach($productId);
            session()->flash('message', 'محصول با موفقیت از لیست شما حذف شد.');
        }
    }

    public function render()
    {
        $supplier = Auth::user()->supplier;

        $products = $supplier->products()
            ->when($this->search, function ($query) {
                $query->where('title_fa', 'like', '%' . $this->search . '%')
                    ->orWhere('title_en', 'like', '%' . $this->search . '%');
            })
            // فیلدهای price و stock_quantity از اینجا حذف شدند
            ->withPivot(['status', 'is_active'])
            ->paginate(10);

        return view('livewire.supplier.my-products', [
            'products' => $products
        ])->layout('components.layouts.supplier');
    }
}
