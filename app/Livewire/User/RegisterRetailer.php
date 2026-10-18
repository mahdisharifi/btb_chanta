<?php

namespace App\Livewire\User;

use App\Models\City;
use App\Models\Province;
use App\Models\Supplier;
use App\Models\SocialNetwork;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rule;


#[Layout('components.layouts.blank')]
class RegisterRetailer extends Component
{
    use WithFileUploads;

    public $company_name;
    public $company_name_en;
    public $brand_name;
    public $description;
    public $logo;
    public $existingLogo;
    public $phone;
    public $mobile;
    public $email;
    public $website;
    public $address;
    public $city_id;
    public $province_id;
    // لیست استان‌ها و شهرها برای فرم
    public $provinces = [];
    public $citiesList = [];

    public $postal_code;
    public $tax_id;
    public $national_id;
    public $social_networks = [];
    public $availableSocialNetworks;

    public $editMode = false;
    public $supplier;

    protected function rules()
    {
        return [
            'company_name'     => 'required|string|max:255',
            'company_name_en'  => 'nullable|string|max:255',
            'brand_name'       => 'nullable|string|max:255',
            'description'      => 'nullable|string|max:1000',
            'logo'             => 'nullable|image|max:2048',
            'phone'            => 'nullable|string|max:20',
            'mobile'           => 'required|string|max:20',
            'email'            => 'nullable|email|max:255',
            'website'          => 'nullable|url|max:255',
            'address'          => 'required|string|max:500',
            'province_id'       => 'required|exists:provinces,id',
            'city_id' => [
                'required',
                Rule::exists('cities', 'id')
                    ->where(fn ($query) => $query->where('province_id', $this->province_id)),
            ],
            'postal_code'      => 'required|string|max:10|regex:/^\d{10}$/',
            'tax_id'           => 'nullable|string|max:20',
            'national_id'      => 'required|string|max:11|regex:/^\d{10,11}$/',
            'social_networks.*' => 'nullable|string|max:255',
        ];
    }

    protected function messages()
    {
        return [
            'company_name.required' => 'نام شرکت الزامی است.',
            'mobile.required'       => 'شماره موبایل الزامی است.',
            'email.email'           => 'ایمیل معتبر نیست.',
            'address.required'      => 'آدرس الزامی است.',
            'province_id.required'     => 'انتخاب استان الزامی است.',
            'city_id.required'         => 'انتخاب شهر الزامی است.',
            'postal_code.required'  => 'کد پستی الزامی است.',
            'postal_code.regex'     => 'کد پستی باید ۱۰ رقم باشد.',
            'national_id.required'  => 'کد ملی الزامی است.',
            'national_id.regex'     => 'کد ملی باید ۱۰ یا ۱۱ رقم باشد.',
            'logo.image'            => 'فایل باید تصویر باشد.',
            'logo.max'              => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.',
        ];
    }

    public function mount()
    {
        $user = Auth::user();
        $this->provinces = Province::all();

        if ($user->supplier) {
            // اگر تامین‌کننده است، به داشبورد تامین‌کننده هدایت شود
            if (! $user->supplier->isRetailer()) {
                return redirect()->route('supplier.dashboard');
            }

            // در غیر این صورت حالت ویرایش برای خرده‌فروش فعال می‌شود
            $this->editMode = true;
            $this->supplier = $user->supplier;
            $this->loadRetailerData();
        }

        $this->availableSocialNetworks = SocialNetwork::active()->get();

        $this->mobile = $this->mobile ?? $user->mobile;
        $this->email  = $this->email ?? $user->email;
    }
    // متد برای دریافت خودکار شهرها هنگام انتخاب استان
    public function updatedProvinceId($value)
    {
        $this->city_id = null;
        if ($value) {
            $this->citiesList = City::where('province_id', $value)->get();
        } else {
            $this->citiesList = [];
        }
    }
    protected function loadRetailerData()
    {
        $this->company_name    = $this->supplier->company_name;
        $this->company_name_en = $this->supplier->company_name_en;
        $this->brand_name      = $this->supplier->brand_name;
        $this->description     = $this->supplier->description;
        $this->existingLogo    = $this->supplier->logo_path;
        $this->phone           = $this->supplier->phone;
        $this->mobile          = $this->supplier->mobile;
        $this->email           = $this->supplier->email;
        $this->website         = $this->supplier->website;
        $this->address         = $this->supplier->address;
        $this->province_id = $this->supplier->province_id;
        $this->city_id = $this->supplier->city_id;
        $this->postal_code     = $this->supplier->postal_code;
        $this->tax_id          = $this->supplier->tax_id;
        $this->national_id     = $this->supplier->national_id;
        $this->social_networks = $this->supplier->social_networks ?? [];
        // بارگذاری شهرهای مرتبط در صورت وجود شناسه استان
        if ($this->province_id) {
            $this->citiesList = City::where('province_id', $this->province_id)->get();
            $this->city_id = $this->citiesList->contains('id', $this->supplier->city_id)
                ? $this->supplier->city_id
                : null;
        }

    }

    public function register()
    {
        $this->validate();
        $logoImages = app(\App\Services\SupplierLogoImageService::class);
        $newLogo = null;
        $logoSaved = false;
        $oldLogoPath = $this->editMode ? $this->supplier?->logo_path : null;
        $oldLogoVersions = $this->editMode ? $this->supplier?->logo_versions : null;

        DB::beginTransaction();

        try {
            $user = Auth::user();

            $data = [
                'company_name'    => $this->company_name,
                'company_name_en' => $this->company_name_en,
                'brand_name'      => $this->brand_name,
                'description'     => $this->description,
                'phone'           => $this->phone,
                'mobile'          => $this->mobile,
                'email'           => $this->email,
                'website'         => $this->website,
                'address'         => $this->address,
                'province_id'     => $this->province_id,
                'city_id'         => $this->city_id,
                'postal_code'     => $this->postal_code,
                'tax_id'          => $this->tax_id,
                'national_id'     => $this->national_id,
                'social_networks' => array_filter($this->social_networks),
            ];
            // مدیریت آپلود لوگو
            if ($this->logo) {
                $newLogo = $logoImages->processUpload($this->logo);
                $data = array_merge($data, $newLogo);
            }

            if ($this->editMode) {
                // فقط اگر قبلاً approved نیست، وضعیت را به under_review تغییر بده
                // خرده‌فروشان تأیید شده می‌توانند اطلاعات را ویرایش کنند بدون نیاز به بررسی مجدد
                if ($this->supplier->verification_status !== 'approved') {
                    $data['verification_status'] = 'under_review';
                }
                $this->supplier->update($data);
                $logoSaved = true;

                DB::commit();
                if ($newLogo && $oldLogoPath) {
                    $logoImages->delete($oldLogoPath, $oldLogoVersions);
                }

                session()->flash('message', 'اطلاعات شما با موفقیت به‌روزرسانی شد.');

                // اگر خرده‌فروش approved است، به داشبورد برود
                if ($this->supplier->verification_status === 'approved') {
                    return redirect()->route('retailer.dashboard');
                }
                
                return redirect()->route('account.pending');
            }

            // حالت ثبت‌نام جدید
            $data['user_id']             = $user->id;
            $data['type']                = 'retailer';
            $data['slug']                = Str::slug($this->company_name . '-' . Str::random(6));
            $data['verification_status'] = 'pending';
            $data['is_active']           = false;

            Supplier::create($data);
            $logoSaved = true;

            $user->syncRoles('retailer_basic');

            DB::commit();

            session()->flash('message', 'ثبت‌نام شما با موفقیت انجام شد. پروفایل شما در حال بررسی است.');

            return redirect()->route('retailer.dashboard');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($newLogo && ! $logoSaved) {
                $logoImages->delete($newLogo['logo_path'] ?? null, $newLogo['logo_versions'] ?? null);
            }
            session()->flash('error', 'خطا در ثبت اطلاعات: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.user.register-retailer')
            ->layout('components.layouts.guest');
    }
}
