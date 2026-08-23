<?php

namespace App\Livewire\User;

use App\Models\Supplier;
use App\Models\SocialNetwork;
use App\Models\Province;
use App\Models\City;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.blank')]
class RegisterSupplier extends Component
{
    use WithFileUploads;

    // اطلاعات شرکت
    public $company_name;
    public $company_name_en;
    public $brand_name;
    public $slug;
    public $description;
    public $logo;
    public $existingLogo;

    // اطلاعات تماس
    public $phone;
    public $mobile;
    public $email;
    public $website;

    // آدرس
    public $address;
    public $postal_code;
    public $city_id;
    public $province_id;

    // لیست استان‌ها و شهرها برای فرم
    public $provinces = [];
    public $citiesList = [];

    // اطلاعات قانونی
    public $national_id;
    public $tax_id;

    // مدارک احراز هویت
    public $national_card_file;
    public $business_license_file;
    public $existingNationalCard;
    public $existingBusinessLicense;

    // شبکه‌های اجتماعی
    public $social_networks = [];
    public $availableSocialNetworks;

    public $editMode = false;
    public $supplier;

    protected function rules()
    {
        $supplierId = $this->supplier->id ?? null;
        
        return [
            'company_name' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('suppliers', 'company_name')->ignore($supplierId)
            ],
            'mobile' => 'required|string|max:20',
            'address' => 'required|string',
            'province_id' => 'required|exists:provinces,id',
            'city_id' => 'required|exists:cities,id',
            'email' => 'nullable|email|max:255',
            'postal_code' => 'nullable|string|max:10',
            'company_name_en' => 'nullable|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                \Illuminate\Validation\Rule::unique('suppliers', 'slug')->ignore($supplierId)
            ],
            'description' => 'nullable|string',
            'logo' => 'nullable|image|max:2048',
            'phone' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'national_id' => 'nullable|string|max:10',
            'tax_id' => 'nullable|string|max:50',
            'national_card_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'business_license_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'social_networks.*' => 'nullable|string|max:255',
        ];
    }

    protected function messages()
    {
        return [
            'company_name.required' => 'نام شرکت الزامی است.',
            'company_name.unique'   => 'این نام شرکت قبلاً ثبت شده است.',
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
            'slug.unique'           => 'این اسلاگ قبلاً استفاده شده است. لطفاً اسلاگ دیگری انتخاب کنید.',
            'slug.regex'            => 'اسلاگ فقط می‌تواند شامل حروف کوچک انگلیسی، اعداد و خط تیره باشد.',
        ];
    }

    public function mount()
    {
        $user = Auth::user();

        // بارگذاری لیست استان‌ها
        $this->provinces = Province::all();

        // بارگذاری شبکه‌های اجتماعی فعال
        $this->availableSocialNetworks = SocialNetwork::where('is_active', true)->get();

        // بررسی و ایجاد رکورد supplier برای ادمین
        if ($user->hasRole(['admin', 'super_admin']) && !$user->supplier) {
            $this->supplier = Supplier::create([
                'user_id' => $user->id,
                'type' => 'admin',
                'verification_status' => 'approved',
                'is_active' => true,
                'is_verified' => true,
                'verified_at' => now(),
                'verified_by' => $user->id,
                'company_name' => 'پنل مدیریت',
                'mobile' => $user->mobile,
                'email' => $user->email,
            ]);
            $this->editMode = true;
            $this->loadSupplierData();
        }
        // بارگذاری supplier موجود (در صورت وجود)
        elseif ($user->supplier) {
            $this->editMode = true;
            $this->supplier = $user->supplier;
            $this->loadSupplierData();
        }

        // پر کردن mobile و email از user
        $this->mobile = $this->mobile ?? $user->mobile;
        $this->email = $this->email ?? $user->email;
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

    protected function loadSupplierData()
    {
        $this->company_name = $this->supplier->company_name;
        $this->company_name_en = $this->supplier->company_name_en;
        $this->brand_name = $this->supplier->brand_name;
        $this->slug = $this->supplier->slug;
        $this->description = $this->supplier->description;
        $this->existingLogo = $this->supplier->logo_path;
        $this->phone = $this->supplier->phone;
        $this->mobile = $this->supplier->mobile;
        $this->email = $this->supplier->email;
        $this->website = $this->supplier->website;
        $this->address = $this->supplier->address;
        $this->postal_code = $this->supplier->postal_code;

        $this->province_id = $this->supplier->province_id;
        $this->city_id = $this->supplier->city_id;

        // بارگذاری شهرهای مرتبط در صورت وجود شناسه استان
        if ($this->province_id) {
            $this->citiesList = City::where('province_id', $this->province_id)->get();
        }

        $this->national_id = $this->supplier->national_id;
        $this->tax_id = $this->supplier->tax_id;
        $this->existingNationalCard = $this->supplier->national_card_file;
        $this->existingBusinessLicense = $this->supplier->business_license_file;

        // بارگذاری شبکه‌های اجتماعی از JSON
        $this->social_networks = $this->supplier->social_networks ?? [];
    }

    public function register()
    {
        $this->validate();

        try {
            $data = [
                'company_name' => $this->company_name,
                'company_name_en' => $this->company_name_en,
                'brand_name' => $this->brand_name,
                'description' => $this->description,
                'phone' => $this->phone,
                'mobile' => $this->mobile,
                'email' => $this->email,
                'website' => $this->website,
                'address' => $this->address,
                'postal_code' => $this->postal_code,
                'province_id' => $this->province_id,
                'city_id' => $this->city_id,
                'national_id' => $this->national_id,
                'tax_id' => $this->tax_id,
            ];

            // مدیریت slug
            if ($this->editMode) {
                // حالت ویرایش: فقط اگر کاربر slug جدیدی وارد کرده باشد
                if ($this->slug && $this->slug !== $this->supplier->slug) {
                    $data['slug'] = Str::slug($this->slug);
                }
                // در غیر این صورت slug تغییر نمی‌کند
            } else {
                // حالت ثبت‌نام جدید: از company_name یا slug دستی
                $data['slug'] = $this->slug ? Str::slug($this->slug) : Str::slug($this->company_name);
            }

            // ذخیره لوگو
            if ($this->logo) {
                if ($this->existingLogo) {
                    Storage::disk('public')->delete($this->existingLogo);
                }
                $data['logo_path'] = $this->logo->store('suppliers/logos', 'public');
            }

            // ذخیره مدارک احراز هویت
            if ($this->national_card_file) {
                if ($this->existingNationalCard) {
                    Storage::disk('public')->delete($this->existingNationalCard);
                }
                $data['national_card_file'] = $this->national_card_file->store('suppliers/documents', 'public');
            }

            if ($this->business_license_file) {
                if ($this->existingBusinessLicense) {
                    Storage::disk('public')->delete($this->existingBusinessLicense);
                }
                $data['business_license_file'] = $this->business_license_file->store('suppliers/documents', 'public');
            }

            // ذخیره شبکه‌های اجتماعی به‌صورت JSON
            $data['social_networks'] = array_filter($this->social_networks);

            if ($this->editMode) {
                // --- اجرای واقعی قفل سمت سرور ---
                // فیلدهای قفل‌شده (هویتی/قانونی) پس از تایید نهایی
                // به‌هیچ‌وجه از سمت کاربر به‌روزرسانی نمی‌شوند، حتی اگر
                // payload دستکاری شده باشد.
                foreach ($this->supplier->lockedFields() as $lockedField) {
                    unset($data[$lockedField]);
                }

                // برای ادمین، وضعیت تایید تغییر نکند
                if ($this->supplier->type !== 'admin') {
                    // فقط اگر قبلاً approved نیست، وضعیت را به under_review تغییر بده
                    // تأمین‌کنندگان تأیید شده می‌توانند اطلاعات را ویرایش کنند بدون نیاز به بررسی مجدد
                    if ($this->supplier->verification_status !== 'approved') {
                        $data['verification_status'] = 'under_review';
                    }
                    session()->flash('message', 'اطلاعات شما با موفقیت به‌روزرسانی شد.');
                } else {
                    session()->flash('message', 'اطلاعات فروشگاه با موفقیت به‌روزرسانی شد.');
                }

                $this->supplier->update($data);
            } else {
                // حالت ثبت‌نام جدید
                $data['user_id'] = Auth::id();
                $data['type'] = 'supplier';
                $data['verification_status'] = 'pending';
                $data['is_active'] = false;

                $this->supplier = Supplier::create($data);

                // تخصیص نقش
                Auth::user()->syncRoles('regular_supplier');

                session()->flash('message', 'ثبت‌نام شما با موفقیت انجام شد. پس از تایید مدیر، می‌توانید از سیستم استفاده کنید.');
            }

            // ریدایرکت بر اساس نوع و وضعیت تایید
            if ($this->supplier->type === 'admin') {
                return redirect()->route('admin.dashboard');
            }

            // اگر تأمین‌کننده approved است، به داشبورد برود
            if ($this->supplier->verification_status === 'approved') {
                return redirect()->route('supplier.dashboard');
            }

            return redirect()->route('account.pending');

        } catch (\Exception $e) {
            session()->flash('error', 'خطا در ثبت اطلاعات: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.user.register-supplier');
    }
}
