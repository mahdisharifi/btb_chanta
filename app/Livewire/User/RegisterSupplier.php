<?php

namespace App\Livewire\User;

use App\Models\Supplier;
use App\Models\SocialNetwork;
use App\Models\Province;
use App\Models\City;
use App\Models\User;
use App\Models\SupplierSlugRedirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
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
    #[Locked]
    public $existingNationalCard;
    #[Locked]
    public $existingBusinessLicense;

    // شبکه‌های اجتماعی
    public $social_networks = [];
    public $availableSocialNetworks;

    #[Locked]
    public $editMode = false;

    #[Locked]
    public $supplier;

    #[Locked]
    public bool $adminMode = false;

    #[Locked]
    public ?int $selectedUserId = null;

    #[Locked]
    public string $selectedUserName = '';

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
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                \Illuminate\Validation\Rule::unique('suppliers', 'slug')->ignore($supplierId),
                \Illuminate\Validation\Rule::unique('supplier_slug_redirects', 'old_slug'),
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
        abort_unless($user, 403);
        $this->provinces = Province::all();
        $this->availableSocialNetworks = SocialNetwork::where('is_active', true)->get();

        if (request()->query('source') === 'admin' || request()->has('user_id')) {
            Gate::authorize('create', Supplier::class);
            $target = User::findOrFail(request()->integer('user_id'));
            abort_if(Supplier::withTrashed()->where('user_id', $target->id)->exists(), 409);
            $this->adminMode = true;
            $this->selectedUserId = $target->id;
            $this->selectedUserName = $target->name;
            $this->mobile = $target->mobile;
            $this->email = $target->email;

            return;
        }

        if ($this->supplier || $user->supplier) {
            $this->supplier = $this->supplier ?: $user->supplier;
            Gate::authorize('update', $this->supplier);
            $this->editMode = true;
            $this->loadSupplierData();
        }

        $this->mobile = $this->mobile ?? $user->mobile;
        $this->email = $this->email ?? $user->email;
    }

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
        if ($this->adminMode) {
            Gate::authorize('create', Supplier::class);
            abort_unless($this->selectedUserId && ! $this->editMode, 403);
        } elseif ($this->editMode) {
            Gate::authorize('update', $this->supplier);
        }

        $this->slug = $this->editMode ? $this->supplier->slug : $this->generateInitialSlug();
        $this->validate();
        $logoImages = app(\App\Services\SupplierLogoImageService::class);
        $newLogo = null;
        $logoSaved = false;
        $newDocuments = [];
        $oldDocuments = $this->editMode ? [
            'national_card_file' => $this->supplier->national_card_file,
            'business_license_file' => $this->supplier->business_license_file,
        ] : [];
        $oldLogoPath = $this->editMode ? $this->supplier?->logo_path : null;
        $oldLogoVersions = $this->editMode ? $this->supplier?->logo_versions : null;

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
                unset($data['slug']);
            } else {
                // حالت ثبت‌نام جدید: از company_name یا slug دستی
                $data['slug'] = $this->slug ? Str::slug($this->slug) : Str::slug($this->company_name);
            }

            // ذخیره لوگو
            if ($this->logo) {
                $newLogo = $logoImages->processUpload($this->logo);
                $data = array_merge($data, $newLogo);
            }

            // ذخیره مدارک احراز هویت
            if ($this->national_card_file) {
                $documentPath = $this->national_card_file->store('suppliers/documents', 'public');
                if (! is_string($documentPath) || $documentPath === '') {
                    throw new \RuntimeException('Supplier document upload failed.');
                }
                $newDocuments['national_card_file'] = $data['national_card_file'] = $documentPath;
            }

            if ($this->business_license_file) {
                $documentPath = $this->business_license_file->store('suppliers/documents', 'public');
                if (! is_string($documentPath) || $documentPath === '') {
                    throw new \RuntimeException('Supplier document upload failed.');
                }
                $newDocuments['business_license_file'] = $data['business_license_file'] = $documentPath;
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

                if ($newLogo && ! array_key_exists('logo_path', $data)) {
                    $logoImages->delete($newLogo['logo_path'] ?? null, $newLogo['logo_versions'] ?? null);
                    $newLogo = null;
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
                $logoSaved = true;
            } else {
                // حالت ثبت‌نام جدید
                $data['user_id'] = $this->adminMode ? $this->selectedUserId : Auth::id();
                $data['type'] = 'supplier';
                $data['verification_status'] = 'pending';
                $data['is_active'] = false;

                $this->supplier = DB::transaction(function () use ($data) {
                    $target = User::query()->lockForUpdate()->findOrFail($data['user_id']);
                    if (Supplier::withTrashed()->where('user_id', $target->id)->exists()) {
                        throw ValidationException::withMessages([
                            'company_name' => 'این کاربر قبلاً پروفایل تأمین‌کننده دارد.',
                        ]);
                    }
                    $supplier = Supplier::create($data);
                    if ($this->adminMode || $target->hasAnyRole(['super_admin', 'admin'])) {
                        $target->assignRole('regular_supplier');
                    } else {
                        $target->syncRoles('regular_supplier');
                    }

                    return $supplier;
                });
                $logoSaved = true;

                session()->flash('message', 'ثبت‌نام شما با موفقیت انجام شد. پس از تایید مدیر، می‌توانید از سیستم استفاده کنید.');
            }

            foreach ($newDocuments as $field => $path) {
                if ($this->supplier->getAttribute($field) === $path) {
                    if (filled($oldDocuments[$field] ?? null) && $oldDocuments[$field] !== $path) {
                        Storage::disk('public')->delete($oldDocuments[$field]);
                    }
                } else {
                    Storage::disk('public')->delete($path);
                }
            }

            if ($newLogo && $logoSaved && $oldLogoPath) {
                $logoImages->delete($oldLogoPath, $oldLogoVersions);
            }

            // ریدایرکت بر اساس نوع و وضعیت تایید
            if ($this->adminMode) {
                return redirect()->route('admin.suppliers.index');
            }

            if ($this->supplier->type === 'admin') {
                return redirect()->route('admin.dashboard');
            }

            // اگر تأمین‌کننده approved است، به داشبورد برود
            if ($this->supplier->verification_status === 'approved') {
                return redirect()->route('supplier.dashboard');
            }

            return redirect()->route('account.pending');

        } catch (\Exception $e) {
            if (! $logoSaved) {
                Storage::disk('public')->delete(array_values($newDocuments));
            }
            if ($newLogo && ! $logoSaved) {
                $logoImages->delete($newLogo['logo_path'] ?? null, $newLogo['logo_versions'] ?? null);
            }
            if ($e instanceof \Illuminate\Database\UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['slug' => 'آدرس یا اطلاعات تأمین‌کننده هم‌زمان تغییر کرده است؛ دوباره تلاش کنید.']);
            }
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);
            session()->flash('error', 'ثبت اطلاعات انجام نشد. لطفاً دوباره تلاش کنید.');
        }
    }

    protected function generateInitialSlug(): string
    {
        $base = Str::slug((string) $this->company_name);
        $base = $base !== '' ? rtrim(substr($base, 0, 191), '-') : 'supplier-'.Str::lower(Str::random(12));
        $candidate = $base;
        $suffix = 2;

        while (Supplier::withTrashed()->where('slug', $candidate)->exists()
            || SupplierSlugRedirect::where('old_slug', $candidate)->exists()) {
            $suffixText = '-'.$suffix++;
            $candidate = rtrim(substr($base, 0, 191 - strlen($suffixText)), '-').$suffixText;
        }

        return $candidate;
    }

    public function render()
    {
        return view('livewire.user.register-supplier');
    }
}
