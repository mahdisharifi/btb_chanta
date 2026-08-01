<?php

namespace App\Livewire\Web;

use Livewire\Component;
use Livewire\WithPagination;
use App\Services\AdvancedSearchService;
use App\Models\Category;
use App\Enums\PriceType;

class AdvancedSearch extends Component
{
    use WithPagination;

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

    protected $queryString = [
        'query' => ['except' => ''],
        'categoryId' => ['except' => ''],
        'selectedAttributes' => ['except' => []],
        'supplierId' => ['except' => ''],
        'priceType' => ['except' => ''],
        'minPrice' => ['except' => ''],
        'maxPrice' => ['except' => ''],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount()
    {
        $this->categories = Category::active()->get();
        $this->priceTypes = PriceType::cases();

        $service = new AdvancedSearchService();
        $this->priceRange = $service->getFilterOptions(null, auth()->user())['price_range'];

        if ($this->categoryId) {
            $this->loadFilterOptions();
        }
    }

    public function updatedCategoryId($value)
    {
        $this->resetPage();
        $this->selectedAttributes = [];
        $this->supplierId = null;

        if ($value) {
            $this->loadFilterOptions();
        } else {
            $this->filterAttributes = [];
            $this->suppliers = [];
        }
    }

    public function updated($property)
    {
        if (in_array($property, [
            'query',
            'selectedAttributes',
            'supplierId',
            'priceType',
            'minPrice',
            'maxPrice',
            'sortBy',
            'sortDirection'
        ])) {
            $this->resetPage();
        }
    }

    private function loadFilterOptions()
    {
        $service = new AdvancedSearchService();
        $options = $service->getFilterOptions($this->categoryId, auth()->user());

        $this->filterAttributes = $options['attributes'];
        $this->suppliers = $options['suppliers'];
        $this->priceRange = $options['price_range'];
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
        $this->resetPage();
        $this->filterAttributes = [];
        $this->suppliers = [];
    }

    public function render()
    {
        $service = new AdvancedSearchService();

        $filters = [
            'q' => $this->query,
            'category_id' => $this->categoryId,
            'attributes' => $this->selectedAttributes,
            'supplier_id' => $this->supplierId,
            'price_type' => $this->priceType,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];

        $products = $service->searchProducts($filters, auth()->user());

        return view('livewire.web.advanced-search', [
            'products' => $products,
        ]);
    }
}
