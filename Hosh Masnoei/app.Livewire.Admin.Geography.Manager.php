<?php

namespace App\Livewire\Admin\Geography;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\Province;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Manager extends Component
{
    use WithPagination;

    public string $provinceSearch = '';
    public string $citySearch = '';

    public ?int $selectedProvinceId = null;

    // ---- Province modal state ----
    public bool $showProvinceModal = false;
    public ?int $editingProvinceId = null;
    public array $provinceForm = [];

    // ---- City modal state ----
    public bool $showCityModal = false;
    public ?int $editingCityId = null;
    public array $cityForm = [];

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['super_admin', 'admin']),
            403
        );
        $this->resetProvinceForm();
        $this->resetCityForm();
    }

    /* ===================== Helpers ===================== */

    private function defaultCountryId(): ?int
    {
        return (int) DB::table('countries')->orderBy('id')->value('id') ?: null;
    }

    private function resetProvinceForm(): void
    {
        $this->editingProvinceId = null;
        $this->provinceForm = [
            'country_id'  => $this->defaultCountryId(),
            'name'        => '',
            'native_name' => '',
            'iso_code'    => '',
            'capital'     => '',
            'latitude'    => null,
            'longitude'   => null,
            'is_active'   => true,
            'sort_order'  => 0,
        ];
    }

    private function resetCityForm(): void
    {
        $this->editingCityId = null;
        $this->cityForm = [
            'name'                => '',
            'native_name'         => '',
            'postal_code_pattern' => '',
            'latitude'            => null,
            'longitude'           => null,
            'population'          => null,
            'is_active'           => true,
            'sort_order'          => 0,
        ];
    }

    protected function provinceRules(): array
    {
        return [
            'provinceForm.country_id'  => ['required', 'integer', 'exists:countries,id'],
            'provinceForm.name'        => ['required', 'string', 'max:100'],
            'provinceForm.native_name' => ['nullable', 'string', 'max:100'],
            'provinceForm.iso_code'    => ['nullable', 'string', 'max:10'],
            'provinceForm.capital'     => ['nullable', 'string', 'max:100'],
            'provinceForm.latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'provinceForm.longitude'   => ['nullable', 'numeric', 'between:-180,180'],
            'provinceForm.is_active'   => ['boolean'],
            'provinceForm.sort_order'  => ['integer', 'min:0'],
        ];
    }

    protected function cityRules(): array
    {
        return [
            'cityForm.name'                => ['required', 'string', 'max:100'],
            'cityForm.native_name'         => ['nullable', 'string', 'max:100'],
            'cityForm.postal_code_pattern' => ['nullable', 'string', 'max:50'],
            'cityForm.latitude'            => ['nullable', 'numeric', 'between:-90,90'],
            'cityForm.longitude'           => ['nullable', 'numeric', 'between:-180,180'],
            'cityForm.population'          => ['nullable', 'integer', 'min:0'],
            'cityForm.is_active'           => ['boolean'],
            'cityForm.sort_order'          => ['integer', 'min:0'],
        ];
    }

    /* ===================== Selection ===================== */

    public function selectProvince(int $id): void
    {
        $this->selectedProvinceId = $id;
        $this->citySearch = '';
        $this->resetPage();
    }

    public function updatedCitySearch(): void
    {
        $this->resetPage();
    }

    /* ===================== Province CRUD ===================== */

    public function createProvince(): void
    {
        $this->resetProvinceForm();
        $this->resetValidation();
        $this->showProvinceModal = true;
    }

    public function editProvince(int $id): void
    {
        $province = Province::findOrFail($id);

        $this->editingProvinceId = $province->id;
        $this->provinceForm = [
            'country_id'  => $province->country_id,
            'name'        => $province->name,
            'native_name' => $province->native_name,
            'iso_code'    => $province->iso_code,
            'capital'     => $province->capital,
            'latitude'    => $province->latitude,
            'longitude'   => $province->longitude,
            'is_active'   => (bool) $province->is_active,
            'sort_order'  => (int) $province->sort_order,
        ];

        $this->resetValidation();
        $this->showProvinceModal = true;
    }

    public function saveProvince(): void
    {
        $data = $this->validate($this->provinceRules())['provinceForm'];

        if ($this->editingProvinceId) {
            Province::findOrFail($this->editingProvinceId)->update($data);
            $this->dispatch('toast', message: 'استان با موفقیت بروزرسانی شد.', type: 'success');
        } else {
            $province = Province::create($data);
            $this->selectedProvinceId = $province->id;
            $this->dispatch('toast', message: 'استان جدید ایجاد شد.', type: 'success');
        }

        $this->showProvinceModal = false;
        $this->resetProvinceForm();
    }

    public function deleteProvince(int $id): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $province = Province::findOrFail($id);

        $userCount = DB::table('user_profiles')->where('province_id', $id)->count();
        if ($userCount > 0) {
            $this->dispatch('toast', type: 'error',
                message: "این استان {$userCount} کاربر وابسته دارد و قابل حذف نیست.");
            return;
        }

        $province->delete(); // SoftDelete (مدل Province از SoftDeletes استفاده می‌کند)

        if ($this->selectedProvinceId === $id) {
            $this->selectedProvinceId = null;
        }

        $this->dispatch('toast', type: 'success', message: 'استان با موفقیت غیرفعال/حذف نرم شد.');
    }
    /* ===================== City CRUD ===================== */

    public function createCity(): void
    {
        if (! $this->selectedProvinceId) {
            $this->dispatch('toast', message: 'ابتدا یک استان را انتخاب کنید.', type: 'error');
            return;
        }

        $this->resetCityForm();
        $this->resetValidation();
        $this->showCityModal = true;
    }

    public function editCity(int $id): void
    {
        $city = City::findOrFail($id);

        $this->editingCityId = $city->id;
        $this->cityForm = [
            'name'                => $city->name,
            'native_name'         => $city->native_name,
            'postal_code_pattern' => $city->postal_code_pattern,
            'latitude'            => $city->latitude,
            'longitude'           => $city->longitude,
            'population'          => $city->population,
            'is_active'           => (bool) $city->is_active,
            'sort_order'          => (int) $city->sort_order,
        ];

        $this->resetValidation();
        $this->showCityModal = true;
    }

    public function saveCity(): void
    {
        abort_unless($this->selectedProvinceId, 422, 'استان انتخاب نشده است.');

        $data = $this->validate($this->cityRules())['cityForm'];

        $province = Province::findOrFail($this->selectedProvinceId);
        $data['province_id'] = $province->id;
        $data['country_id']  = $province->country_id; // ارث‌بری از استان والد

        if ($this->editingCityId) {
            City::findOrFail($this->editingCityId)->update($data);
            $this->dispatch('toast', message: 'شهر بروزرسانی شد.', type: 'success');
        } else {
            City::create($data);
            $this->dispatch('toast', message: 'شهر جدید ایجاد شد.', type: 'success');
        }

        $this->showCityModal = false;
        $this->resetCityForm();
    }

    public function deleteCity(int $id): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $city = City::findOrFail($id);

        $userCount = DB::table('user_profiles')->where('city_id', $id)->count();
        if ($userCount > 0) {
            $this->dispatch('toast', type: 'error',
                message: "این شهر {$userCount} کاربر وابسته دارد و قابل حذف نیست.");
            return;
        }

        $city->delete(); // SoftDelete

        $this->dispatch('toast', type: 'success', message: 'شهر با موفقیت حذف نرم شد.');
    }



    /**
     * شمارش کاربران به تفکیک نقش برای استان یا شهر.
     *
     * @param  string $column  'province_id' یا 'city_id'
     * @return array<int, array<string,int>>  [geoId => [roleName => count]]
     */
    protected function userRoleCounts(string $column): array
    {
        // فقط ستون‌های مجاز تا از تزریق نام ستون جلوگیری شود (امنیت)
        if (! in_array($column, ['province_id', 'city_id'], true)) {
            return [];
        }

        $rows = DB::table('user_profiles')
            ->join('model_has_roles', function ($join) {
                $join->on('user_profiles.user_id', '=', 'model_has_roles.model_id')
                    // model_type برای Spatie به‌صورت پیش‌فرض نام کامل کلاس است.
                    // اگر در پروژه morphMap تعریف شده، این مقدار باید مطابق آن باشد.
                    ->where('model_has_roles.model_type', '=', User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereNotNull("user_profiles.$column")
            ->select(
                "user_profiles.$column as geo_id",
                'roles.name as role',
                DB::raw('COUNT(DISTINCT user_profiles.user_id) as total')
            )
            ->groupBy("user_profiles.$column", 'roles.name')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->geo_id][$row->role] = (int) $row->total;
        }

        return $map;
    }
    /* ===================== Render ===================== */

    public function render(): View
    {
        $provinces = Province::query()
            ->when($this->provinceSearch !== '', function ($q) {
                $term = "%{$this->provinceSearch}%";
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('native_name', 'like', $term));
            })
            ->withCount('cities')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $cities = null;
        if ($this->selectedProvinceId) {
            $cities = City::query()
                ->where('province_id', $this->selectedProvinceId)
                ->when($this->citySearch !== '', function ($q) {
                    $term = "%{$this->citySearch}%";
                    $q->where(fn ($w) => $w->where('name', 'like', $term)
                        ->orWhere('native_name', 'like', $term));
                })
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15);
        }

        $countries = DB::table('countries')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('livewire.admin.geography.manager', [
            'provinces' => $provinces,
            'cities'    => $cities,
            'countries' => $countries,
            'provinceUserCounts' => $this->userRoleCounts('province_id'),
            'cityUserCounts'     => $this->userRoleCounts('city_id'),
        ]);
    }

}
