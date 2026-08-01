<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('داشبورد مدیریت')]
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.admin.dashboard', [
            // آمارهای کلی
            'usersCount' => User::count(),
            'productsCount' => Product::count(),
            'categoriesCount' => Category::count(),
            'suppliersCount' => Supplier::count(),

            // داده‌های اخیر برای نمایش در جداول داشبورد
            'latestUsers' => User::latest()->take(5)->get(),
            'latestProducts' => Product::with('category')->latest()->take(5)->get(),
        ]);
    }
}
