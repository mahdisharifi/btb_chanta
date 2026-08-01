<?php

namespace App\Livewire\Admin\Attributes;

use App\Models\Attribute;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

#[Layout('components.layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $typeFilter = '';
    public $sortField = 'id';
    public $sortAsc = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'sortField' => ['except' => 'id'],
        'sortAsc' => ['except' => false],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTypeFilter()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortAsc = !$this->sortAsc;
        } else {
            $this->sortField = $field;
            $this->sortAsc = true;
        }
    }

    public function deleteAttribute($id)
    {
        // در صورت نیاز به استفاده از پالیسی‌ها یا گیت‌ها:
        // Gate::authorize('delete-attribute');

        $attribute = Attribute::findOrFail($id);

        // واکشی شناسه‌های مقادیر ویژگی
        $valueIds = DB::table('attribute_values')
            ->where('attribute_id', $attribute->id)
            ->pluck('id');

        // بررسی اینکه آیا این ویژگی به محصولی متصل است یا خیر
        $hasProducts = DB::table('attribute_product')
            ->whereIn('attribute_value_id', $valueIds)
            ->exists();

        if ($hasProducts) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'این ویژگی به محصولات متصل است و امکان حذف آن وجود ندارد.',
            ]);
            return;
        }

        // انجام عملیات حذف به صورت تراکنشی جهت حفظ یکپارچگی داده‌ها
        DB::transaction(function () use ($attribute, $valueIds) {
            // حذف اتصالات دسته بندی
            DB::table('attribute_category')
                ->where('attribute_id', $attribute->id)
                ->delete();

            // حذف مقادیر ویژگی
            if ($valueIds->isNotEmpty()) {
                DB::table('attribute_values')
                    ->whereIn('id', $valueIds)
                    ->delete();
            }

            // حذف خود ویژگی
            $attribute->delete();
        });

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'ویژگی با موفقیت حذف شد.',
        ]);
    }

    public function render()
    {
        $query = Attribute::query()
            ->select('attributes.*')
            ->addSelect([
                'products_count' => DB::table('attribute_product')
                    ->join('attribute_values', 'attribute_product.attribute_value_id', '=', 'attribute_values.id')
                    ->whereColumn('attribute_values.attribute_id', 'attributes.id')
                    ->select(DB::raw('count(distinct attribute_product.product_id)'))
            ])
            ->when($this->search, function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            })
            ->when($this->typeFilter, function ($q) {
                $q->where('type', $this->typeFilter);
            });

        // اعمال منطق مرتب‌سازی
        if ($this->sortField === 'products_count') {
            $query->orderBy('products_count', $this->sortAsc ? 'asc' : 'desc');
        } else {
            $query->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc');
        }

        return view('livewire.admin.attributes.index', [
            'attributes' => $query->paginate(100),
        ]);
    }
}
