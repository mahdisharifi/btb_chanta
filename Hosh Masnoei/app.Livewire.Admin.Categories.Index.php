<?php

namespace App\Livewire\Admin\Categories;

use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Gate;
use App\Livewire\Admin\Categories\Create;

#[Layout('components.layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public $parentId = null;
    public string $viewMode = 'tree';

    // اصلاح: اضافه شدن مدیریت وضعیت دسته‌های باز شده
    public array $expandedCategories = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'parentId' => ['except' => null],
        'viewMode' => ['except' => 'tree'],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingParentId(): void { $this->resetPage(); }

    // اصلاح: متد تغییر وضعیت نمایش زیرمجموعه‌ها
    public function toggleCategory($id): void
    {
        if (in_array($id, $this->expandedCategories)) {
            $this->expandedCategories = array_diff($this->expandedCategories, [$id]);
        } else {
            $this->expandedCategories[] = $id;
        }
    }
    // متد جدید اضافه شده
    public function toggleView(string $mode): void
    {
        $this->viewMode = $mode;
        $this->resetPage();
    }
    public function deleteCategory($id): void
    {
        Gate::authorize('delete', Category::class);
        $category = Category::findOrFail($id);

        if ($category->allChildren()->exists()) {
            session()->flash('error', 'این دسته‌بندی دارای زیردسته است و قابل حذف نیست.');
            return;
        }

        $category->delete();
        session()->flash('success', 'دسته‌بندی با موفقیت حذف شد.');
    }

    public function render()
    {
        Gate::authorize('viewAny', Category::class);

        // بهینه‌سازی کوئری برای هاست اشتراکی
        $query = Category::query()
            ->withCount('products');

        if ($this->viewMode === 'table') {
            $categories = $query->with('parent')
                ->when($this->search, function ($q) {
                    $q->where(fn($inner) => $inner->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('slug', 'like', '%' . $this->search . '%'));
                })
                ->when($this->parentId, function ($q) {
                    $this->parentId === 'root' ? $q->whereNull('parent_id') : $q->where('parent_id', $this->parentId);
                })
                ->orderBy('sort_order', 'asc')
                ->paginate(25);
        } else {
            // منطق درختی هوشمند
            if ($this->search) {
                // در هنگام جستجو، تمام موارد منطبق را بدون ساختار درختی نمایش می‌دهیم تا گم نشوند
                $categories = $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('slug', 'like', '%' . $this->search . '%')
                    ->orderBy('sort_order', 'asc')
                    ->get();
            } else {
                $categories = Category::whereNull('parent_id')
                    ->with(['allChildrenRecursive' => fn($q) => $q->withCount('products')])
                    ->withCount('products')
                    ->orderBy('sort_order', 'asc')
                    ->get();
            }
        }

        return view('livewire.admin.categories.index', [
            'categories' => $categories,
            'rootCategories' => Category::whereNull('parent_id')->orderBy('sort_order')->get(),
            'icons' => Create::ICONS,
        ]);
    }
}
