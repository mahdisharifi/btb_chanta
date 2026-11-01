<?php

namespace App\Livewire\Supplier\Branches;

use App\Models\Supplier;
use App\Models\SupplierBranchProductTransfer;
use App\Services\SupplierBranchPrimaryService;
use App\Services\SupplierBranchTransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.supplier')]
class ManageBranches extends Component
{
    public string $branchName = '';
    public array $branchProvinceIds = [];
    public array $branchCityIds = [];
    public array $branchCityNames = [];
    public array $branchAddresses = [];
    public array $branchPostalCodes = [];

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
            ->withCount(['branchProductTransfers as pending_transfers_count' => fn ($query) => $query->where('status', 'pending')])
            ->withExists(['ownedProducts as has_shared_active_product' => fn ($products) => $products
                ->eligiblePrimarySwitchCopy($this->primarySupplier)])
            ->get();
    }

    public function createBranch(SupplierBranchTransferService $transferService)
    {
        $primary = auth()->user()->supplier()->firstOrFail();
        $this->validate([
            'branchName' => [
                'required',
                'string',
                'max:120',
                Rule::unique('suppliers', 'branch_name')
                    ->where(fn ($query) => $query->where('parent_supplier_id', $primary->id)),
            ],
        ], [
            'branchName.required' => 'نام شعبه را وارد کنید.',
            'branchName.unique' => 'این نام قبلاً برای یکی از شعبه‌های فروشگاه استفاده شده است.',
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
                $branch->branch_name = trim($this->branchName);
                $branch->slug = $this->branchSlug($primary, $this->branchName);
                $branch->user_id = $primary->user_id;
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

    public function deleteBranch(int $supplierId): void
    {
        DB::transaction(function () use ($supplierId): void {
            $branch = $this->primarySupplier->branches()->whereKey($supplierId)->lockForUpdate()->firstOrFail();
            $now = now();

            SupplierBranchProductTransfer::query()
                ->where('target_supplier_id', $branch->id)
                ->where('status', SupplierBranchProductTransfer::PENDING)
                ->update([
                    'status' => SupplierBranchProductTransfer::CANCELLED,
                    'decided_at' => $now,
                    'updated_at' => $now,
                ]);

            $branch->delete();
        }, 3);

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
        unset($this->branches);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'شعبه از فهرست شعب فعال حذف شد.']);
    }

    public function makePrimary(int $supplierId)
    {
        $newPrimary = app(SupplierBranchPrimaryService::class)->promote($this->primarySupplier, $supplierId);
        session()->forget('supplier.active_branch_id');
        unset($this->primarySupplier, $this->branches);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'شعبهٔ انتخاب‌شده به ریشهٔ فروشگاه تبدیل شد؛ نسبت قیمت محصولات مشترک حفظ شد.',
        ]);

        return $this->redirectRoute('supplier.branches.index');
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
