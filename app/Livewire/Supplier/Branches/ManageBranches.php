<?php

namespace App\Livewire\Supplier\Branches;

use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Category;
use App\Services\SupplierBranchPriceSyncService;
use App\Services\SupplierBranchTransferService;
use App\Services\SupplierBranchDeletionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.supplier')]
class ManageBranches extends Component
{
    public string $branchName = '';
    public array $branchProvinceIds = [];
    public string $newBranchAddress = '';
    public string $newBranchPostalCode = '';
    public string $pricingBranchId = '';
    public string $pricingCategoryId = '';
    public string $pricingProductId = '';
    public string $pricingPercentage = '0';
    public array $branchCityIds = [];
    public array $branchCityNames = [];
    public array $branchAddresses = [];
    public array $branchPostalCodes = [];
    #[Locked]
    public ?int $deletingBranchId = null;
    #[Locked]
    public array $branchDeletionSummary = [];
    public bool $removeBranchPrices = false;

    public function mount(): void
    {
        $primary = Supplier::query()
            ->where('user_id', auth()->id())
            ->whereNull('parent_supplier_id')
            ->firstOrFail();
        $suppliers = collect([$primary])->concat(
            $primary->branches()->where('is_active', true)->get(),
        );
        $countryId = $this->iranCountryId();
        $provinceIds = $suppliers->pluck('province_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $cityIds = $suppliers->pluck('city_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $validProvinces = $countryId === null || $provinceIds->isEmpty()
            ? collect()
            : DB::table('provinces')
                ->whereIn('id', $provinceIds)
                ->where('country_id', $countryId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->mapWithKeys(static fn ($id): array => [(int) $id => true]);
        $validCities = $countryId === null || $cityIds->isEmpty()
            ? collect()
            : DB::table('cities')
                ->whereIn('id', $cityIds)
                ->where('country_id', $countryId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->get(['id', 'province_id', 'name', 'native_name'])
                ->keyBy('id');

        foreach ($suppliers as $supplier) {
            $provinceId = (int) ($supplier->province_id ?? 0);
            $cityId = (int) ($supplier->city_id ?? 0);
            $city = $validCities->get($cityId);
            $validProvince = $validProvinces->has($provinceId);
            $validPair = $validProvince && $city && (int) $city->province_id === $provinceId;

            $this->branchProvinceIds[$supplier->id] = $validProvince ? $provinceId : '';
            $this->branchCityIds[$supplier->id] = $validPair ? $cityId : '';
            $this->branchCityNames[$supplier->id] = $validPair
                ? ($city->native_name ?: $city->name)
                : '';
            $this->branchAddresses[$supplier->id] = $supplier->address ?? '';
            $this->branchPostalCodes[$supplier->id] = $supplier->postal_code ?? '';
        }
    }

    private function iranCountryId(): ?int
    {
        $countryId = DB::table('countries')
            ->where('iso2', 'IR')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->value('id');

        return $countryId ? (int) $countryId : null;
    }

    #[Computed]
    public function iranProvinces(): array
    {
        $countryId = $this->iranCountryId();
        if ($countryId === null) {
            return [];
        }

        return DB::table('provinces')
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('native_name')
            ->get(['id', 'name', 'native_name'])
            ->mapWithKeys(static fn ($province): array => [
                (int) $province->id => $province->native_name ?: $province->name,
            ])
            ->all();
    }

    #[Computed]
    public function availableCitiesByProvince(): array
    {
        $countryId = $this->iranCountryId();
        $provinceIds = collect($this->branchProvinceIds)
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($countryId === null || $provinceIds->isEmpty()) {
            return [];
        }

        return DB::table('cities')
            ->whereIn('province_id', $provinceIds)
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('native_name')
            ->get(['id', 'province_id', 'name', 'native_name'])
            ->groupBy('province_id')
            ->map(static fn ($cities): array => $cities->mapWithKeys(static fn ($city): array => [
                (int) $city->id => $city->native_name ?: $city->name,
            ])->all())
            ->all();
    }

    public function updatedBranchProvinceIds($value, string $supplierId): void
    {
        $this->branchCityIds[$supplierId] = '';
        unset($this->availableCitiesByProvince);
        $this->resetErrorBag('branchCityIds.' . $supplierId);
    }

    #[Computed]
    public function primarySupplier(): Supplier
    {
        return auth()->user()->supplier()->firstOrFail();
    }

    #[Computed]
    public function branches()
    {
        return $this->primarySupplier->branches()
            ->where('is_active', true)
            ->withCount(['branchProductTransfers as pending_transfers_count' => fn ($query) => $query->where('status', 'pending')])
            ->get();
    }

    public function createBranch(SupplierBranchTransferService $transferService)
    {
        $primary = auth()->user()->supplier()->firstOrFail();
        $this->branchName = trim($this->branchName);
        $this->validate([
            'branchName' => [
                'required',
                'string',
                'max:120',
                Rule::unique('suppliers', 'branch_name')
                    ->where(fn ($query) => $query->where('parent_supplier_id', $primary->id)
                        ->where('is_active', true)->whereNull('deleted_at')),
            ],
            'branchProvinceIds.new' => ['required', 'integer', Rule::exists('provinces', 'id')->where(fn ($query) => $query
                ->where('country_id', $this->iranCountryId())->where('is_active', true)->whereNull('deleted_at'))],
            'branchCityIds.new' => ['required', 'integer', Rule::exists('cities', 'id')->where(fn ($query) => $query
                ->where('country_id', $this->iranCountryId())->where('province_id', (int) ($this->branchProvinceIds['new'] ?? 0))
                ->where('is_active', true)->whereNull('deleted_at'))],
            'newBranchAddress' => 'nullable|string|max:255',
            'newBranchPostalCode' => 'nullable|string|max:20',
        ], [
            'branchProvinceIds.new.required' => 'استان شعبه را انتخاب کنید.',
            'branchProvinceIds.new.exists' => 'استان انتخاب‌شده معتبر نیست.',
            'branchCityIds.new.required' => 'شهر شعبه را انتخاب کنید.',
            'branchCityIds.new.exists' => 'شهر انتخاب‌شده متعلق به استان انتخابی نیست یا فعال نیست.',
            'branchName.required' => 'نام شعبه را وارد کنید.',
            'branchName.unique' => 'این نام قبلاً برای یکی از شعبه‌های فعال فروشگاه استفاده شده است.',
        ]);

        try {
            $primaryId = (int) $primary->id;
            $ownerId = (int) auth()->id();
            $branch = DB::transaction(function () use ($primaryId, $ownerId, $transferService): Supplier {
                $primary = Supplier::query()
                    ->whereKey($primaryId)
                    ->where('user_id', $ownerId)
                    ->whereNull('parent_supplier_id')
                    ->lockForUpdate()
                    ->firstOrFail();

                $branch = $primary->replicate();
                $branch->parent_supplier_id = $primary->id;
                $branch->branch_pricing_mode = 'independent';
                $branch->branch_price_percentage = null;
                $branch->branch_name = trim($this->branchName);
                $branch->slug = $this->branchSlug($primary, $this->branchName);
                $branch->user_id = $primary->user_id;
                $province = DB::table('provinces')->where('id', $this->branchProvinceIds['new'])->first();
                $city = DB::table('cities')->where('id', $this->branchCityIds['new'])->first();
                $branch->province_id = $province->id;
                $branch->city_id = $city->id;
                $branch->province = $province->native_name ?: $province->name;
                $branch->city = $city->native_name ?: $city->name;
                $branch->address = trim($this->newBranchAddress) ?: null;
                $branch->postal_code = trim($this->newBranchPostalCode) ?: null;
                $branch->save();

                $transferService->seedPendingTransfers($primary, $branch);

                return $branch;
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('branchName', 'ساخت شعبه انجام نشد. دوباره تلاش کنید.');
            return null;
        }

        session()->put('supplier.active_branch_id', $branch->id);
        $this->branchName = '';

        return redirect()->route('supplier.branches.transfers');
    }

    public function updatedPricingBranchId(): void
    {
        $this->pricingCategoryId = '';
        $this->pricingProductId = '';
        unset($this->pricingProducts);
    }

    public function updatedPricingCategoryId(): void
    {
        $this->pricingProductId = '';
        unset($this->pricingProducts);
    }

    #[Computed]
    public function pricingCategories()
    {
        return Category::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function pricingProducts()
    {
        $branch = $this->primarySupplier->branches()->whereKey((int) $this->pricingBranchId)->where('is_active', true)->first();
        if (!$branch) {
            return collect();
        }

        return Product::query()->whereHas('productSuppliers', fn ($query) => $query->where('supplier_id', $branch->id))
            ->when($this->pricingCategoryId !== '', fn ($query) => $query->where('category_id', (int) $this->pricingCategoryId))
            ->orderBy('title')->get(['id', 'title']);
    }

    public function applyBranchPercentage(SupplierBranchPriceSyncService $syncService): void
    {
        $this->validate([
            'pricingBranchId' => 'required|integer',
            'pricingCategoryId' => 'nullable|integer|exists:categories,id',
            'pricingProductId' => 'nullable|integer',
            'pricingPercentage' => 'required|numeric|decimal:0,12|between:-99.999999,999.999999',
        ]);

        $count = DB::transaction(function () use ($syncService): int {
            $primary = Supplier::query()->whereKey($this->primarySupplier->id)
                ->where('user_id', auth()->id())->whereNull('parent_supplier_id')->lockForUpdate()->firstOrFail();
            $branch = $primary->branches()->whereKey((int) $this->pricingBranchId)
                ->where('is_active', true)->lockForUpdate()->firstOrFail();
            abort_unless($branch->usesPrimaryPricing(), 422, 'ابتدا روش قیمت‌گذاری درصدی را در مدیریت محصولات شعبه انتخاب کنید.');
            $products = Product::query()
                ->whereHas('productSuppliers', fn ($query) => $query->where('supplier_id', $branch->id))
                ->when($this->pricingCategoryId !== '', fn ($query) => $query->where('category_id', (int) $this->pricingCategoryId))
                ->when($this->pricingProductId !== '', fn ($query) => $query->whereKey((int) $this->pricingProductId))
                ->lockForUpdate()->get();
            foreach ($products as $product) {
                $offer = ProductSupplier::query()->where('supplier_id', $branch->id)
                    ->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
                $offer->update(['branch_price_percentage' => $this->pricingPercentage]);
                $syncService->syncBranchProduct($product, $branch, (float) $this->pricingPercentage);
            }

            return $products->count();
        }, 3);

        $this->dispatch('notify', ['type' => $count ? 'success' : 'info',
            'message' => $count ? "درصد قیمت برای {$count} محصول شعبه ذخیره شد." : 'محصولی در لیست فروش شعبه با این انتخاب وجود ندارد.']);
    }

    public function saveLocation(int $supplierId): void
    {
        $primary = Supplier::query()
            ->where('user_id', auth()->id())
            ->whereNull('parent_supplier_id')
            ->firstOrFail();
        $target = (int) $primary->id === $supplierId
            ? $primary
            : $primary->branches()->whereKey($supplierId)->where('is_active', true)->firstOrFail();
        $locationOwnerLabel = $target->isBranch()
            ? 'شعبهٔ ' . $target->branchLabel()
            : 'فروشگاه اصلی';

        $provinceField = 'branchProvinceIds.' . $supplierId;
        $cityField = 'branchCityIds.' . $supplierId;
        $addressField = 'branchAddresses.' . $supplierId;
        $postalField = 'branchPostalCodes.' . $supplierId;
        $countryId = $this->iranCountryId();

        if ($countryId === null) {
            $this->addError($provinceField, 'فهرست استان‌ها در دسترس نیست.');
            return;
        }

        $provinceId = (int) ($this->branchProvinceIds[$supplierId] ?? 0);
        $this->validate([
            $provinceField => [
                'required',
                'integer',
                Rule::exists('provinces', 'id')->where(fn ($query) => $query
                    ->where('country_id', $countryId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            $cityField => [
                'required',
                'integer',
                Rule::exists('cities', 'id')->where(fn ($query) => $query
                    ->where('province_id', $provinceId)
                    ->where('country_id', $countryId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            $addressField => 'nullable|string|max:255',
            $postalField => 'nullable|string|max:20',
        ], [
            $provinceField . '.required' => "{$locationOwnerLabel}: استان را انتخاب کنید.",
            $provinceField . '.exists' => "{$locationOwnerLabel}: استان انتخاب‌شده معتبر نیست.",
            $cityField . '.required' => "{$locationOwnerLabel}: شهر را انتخاب کنید.",
            $cityField . '.exists' => "{$locationOwnerLabel}: شهر باید از فهرست استان انتخاب‌شده باشد.",
            $addressField . '.max' => 'نشانی نمی‌تواند بیشتر از ۲۵۵ نویسه باشد.',
            $postalField . '.max' => 'کدپستی نمی‌تواند بیشتر از ۲۰ نویسه باشد.',
        ]);

        $primaryId = (int) $primary->id;

        $cityDisplayName = DB::transaction(function () use ($primaryId, $supplierId, $countryId, $provinceId, $locationOwnerLabel): string {
            $primary = Supplier::query()
                ->whereKey($primaryId)
                ->where('user_id', auth()->id())
                ->whereNull('parent_supplier_id')
                ->lockForUpdate()
                ->firstOrFail();

            $supplier = $supplierId === $primaryId
                ? $primary
                : $primary->branches()
                    ->whereKey($supplierId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();

            $country = DB::table('countries')
                ->where('id', $countryId)
                ->where('iso2', 'IR')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id']);
            $province = DB::table('provinces')
                ->where('id', $provinceId)
                ->where('country_id', $countryId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id', 'name', 'native_name']);
            $city = DB::table('cities')
                ->where('id', (int) ($this->branchCityIds[$supplierId] ?? 0))
                ->where('province_id', $provinceId)
                ->where('country_id', $countryId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id', 'name', 'native_name']);
            abort_unless(
                $country && $province && $city,
                422,
                "استان یا شهر انتخاب‌شده برای {$locationOwnerLabel} دیگر فعال یا معتبر نیست.",
            );

            $supplier->update([
                'province_id' => (int) $province->id,
                'city_id' => (int) $city->id,
                'province' => $province->native_name ?: $province->name,
                'city' => $city->native_name ?: $city->name,
                'address' => trim((string) ($this->branchAddresses[$supplierId] ?? '')) ?: null,
                'postal_code' => trim((string) ($this->branchPostalCodes[$supplierId] ?? '')) ?: null,
            ]);

            return $city->native_name ?: $city->name;
        }, 3);

        $this->branchCityNames[$supplierId] = $cityDisplayName;
        unset($this->primarySupplier, $this->branches, $this->availableCitiesByProvince);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'نشانی شعبه ذخیره شد.']);
    }

    #[Computed]
    public function archivedBranches()
    {
        return $this->primarySupplier->branches()->withTrashed()
            ->where(fn ($query) => $query->where('is_active', false)->orWhereNotNull('deleted_at'))->get();
    }

    public function reviewBranchDeletion(int $supplierId): void
    {
        $this->branchDeletionSummary = app(SupplierBranchDeletionService::class)->summary($this->primarySupplier, $supplierId);
        $this->deletingBranchId = $supplierId;
        $this->removeBranchPrices = false;
        $this->resetErrorBag('branchDeletion');
    }

    public function cancelBranchDeletion(): void
    {
        $this->deletingBranchId = null;
        $this->branchDeletionSummary = [];
        $this->removeBranchPrices = false;
        $this->resetErrorBag('branchDeletion');
    }

    public function confirmBranchDeletion(): void
    {
        if ($this->deletingBranchId === null) {
            return;
        }

        $supplierId = $this->deletingBranchId;
        app(SupplierBranchDeletionService::class)->delete($this->primarySupplier, $supplierId, $this->removeBranchPrices);
        $this->finishBranchDeletion($supplierId);
        $this->cancelBranchDeletion();
    }

    public function deleteBranch(int $supplierId): void
    {
        app(SupplierBranchDeletionService::class)->delete($this->primarySupplier, $supplierId);

        $this->finishBranchDeletion($supplierId);
    }

    private function finishBranchDeletion(int $supplierId): void
    {

        if ((int) session('supplier.active_branch_id') === $supplierId) {
            session()->forget('supplier.active_branch_id');
        }

        unset(
            $this->branchProvinceIds[$supplierId],
            $this->branchCityIds[$supplierId],
            $this->branchCityNames[$supplierId],
            $this->branchAddresses[$supplierId],
            $this->branchPostalCodes[$supplierId],
            $this->availableCitiesByProvince
        );
        unset($this->branches, $this->archivedBranches, $this->pricingProducts);
        if ((int) $this->pricingBranchId === $supplierId) {
            $this->pricingBranchId = '';
            $this->pricingCategoryId = '';
            $this->pricingProductId = '';
        }

        $this->dispatch('notify', ['type' => 'success', 'message' => 'شعبه به‌طور کامل حذف شد؛ خود محصولات حفظ شدند.']);
    }

    /** Reject stale Livewire requests for the retired primary-branch action. */
    public function makePrimary(int $supplierId): void
    {
        abort(403, 'تغییر شعبهٔ اصلی در دسترس نیست.');
    }

    private function branchSlug(Supplier $primary, string $name): string
    {
        $base = Str::slug($primary->slug ?: $primary->name ?: $primary->company_name) ?: 'store';
        $branch = Str::slug($name) ?: 'branch';
        $suffix = Str::lower(Str::random(8));

        return Str::limit($base, 100, '') . '-' . Str::limit($branch, 70, '') . '-' . $suffix;
    }

    public function render()
    {
        return view('livewire.supplier.branches.manage');
    }
}
