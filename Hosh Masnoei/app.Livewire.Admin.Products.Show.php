<?php

namespace App\Livewire\Admin\Products;

use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;
#[Layout('components.layouts.admin')]

class Show extends Component
{
    public Product $product;

    public function mount(Product $product)
    {
        // بارگذاری محصول همراه با روابط برای جلوگیری از N+1 Query
        $this->product = $product->load(['category', 'attributeValues.attribute', 'galleries']);
    }

    public function render()
    {
        return view('livewire.admin.products.show');
    }
}
