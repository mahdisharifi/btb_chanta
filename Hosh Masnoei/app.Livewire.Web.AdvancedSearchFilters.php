<?php

namespace App\Livewire\Web;

use Livewire\Component;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\Supplier;
use App\Enums\PriceType;

class AdvancedSearchFilters extends Component
{
    public $query = '';
    public $categoryId = null;
    public $selectedAttributes = [];
    public $supplierId = null;
    public $priceType = null;
    public $minPrice = null;
    public $maxPrice = null;
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';

    // تغییر نام از $attributes به $filterAttributes
    public $categories = [];
    public $filterAttributes = [];
    public $suppliers = [];
    public $priceTypes = [];
    public $priceRange = [];

    protected $listeners = ['categorySelected' => 'loadFilters'];

    public function mount()
    {
        $this->categories = Category::active()->get();
        $this->priceTypes = PriceType::cases();
    }

    public function loadFilters($categoryId)
    {
        $this->categoryId = $categoryId;

        if ($categoryId) {
            $this->filterAttributes = Attribute::whereHas('categories', function($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            })->with(['values' => function($q) {
                $q->select('attribute_id', 'value')
                    ->distinct()
                    ->orderBy('value');
            }])->get();

            if (auth()->check() && in_array(auth()->user()->role, ['retailer', 'supplier', 'super_admin'])) {
                $this->suppliers = Supplier::whereHas('products', function($q) use ($categoryId) {
                    $q->where('category_id', $categoryId)->active();
                })->active()->select('id', 'name', 'slug')->get();
            }
        } else {
            $this->filterAttributes = [];
            $this->suppliers = [];
        }
    }

    public function updated()
    {
        $this->dispatch('filtersUpdated', [
            'query' => $this->query,
            'categoryId' => $this->categoryId,
            'selectedAttributes' => $this->selectedAttributes,
            'supplierId' => $this->supplierId,
            'priceType' => $this->priceType,
            'minPrice' => $this->minPrice,
            'maxPrice' => $this->maxPrice,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ]);
    }

    public function clearFilters()
    {
        $this->reset([
            'query',
            'categoryId',
            'selectedAttributes',
            'supplierId',
            'priceType',
            'minPrice',
            'maxPrice',
            'sortBy',
            'sortDirection'
        ]);

        $this->filterAttributes = [];
        $this->suppliers = [];

        $this->dispatch('filtersUpdated', [
            'query' => '',
            'categoryId' => null,
            'selectedAttributes' => [],
            'supplierId' => null,
            'priceType' => null,
            'minPrice' => null,
            'maxPrice' => null,
            'sortBy' => 'created_at',
            'sortDirection' => 'desc',
        ]);
    }

    public function render()
    {
        return view('livewire.web.advanced-search-filters');
    }
}
