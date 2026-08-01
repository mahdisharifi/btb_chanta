<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Volt\Volt;


new class extends Component {
    public $name;
    public $email;
    public $current_password;
    public $password;
    public $password_confirmation;

    public function mount()
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function updateProfile()
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($validated);

        $this->dispatch('profile-updated');
        session()->flash('message', 'اطلاعات پروفایل با موفقیت به‌روزرسانی شد.');
    }

    public function updatePassword()
    {
        $user = Auth::user();

        $validated = $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->dispatch('password-updated');
        session()->flash('message', 'رمز عبور با موفقیت تغییر یافت.');
    }

    public function deleteAccount()
    {
        $user = Auth::user();

        Auth::logout();
        $user->delete();

        session()->invalidate();
        session()->regenerateToken();

        return redirect('/');
    }
};

Volt::layout('admin.layouts.admin');

?>

<div>
    <x-slot name="header">
        <h1 class="text-xl font-bold tracking-wider text-gray-200">ویرایش پروفایل</h1>
    </x-slot>

    @section('breadcrumb')
    <li>
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white transition-colors">
            داشبورد
        </a>
    </li>
    <li class="text-gray-500">/</li>
    <li class="text-white">پروفایل</li>
    @endsection

    <div class="py-6">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- بخش اطلاعات شخصی -->
            <div class="mb-6">
                <x-card>
                    <x-slot name="header">
                        <h2 class="text-lg font-semibold text-white">اطلاعات شخصی</h2>
                    </x-slot>

                    <form wire:submit="updateProfile">
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="name" value="نام کامل" />
                                <x-input
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    wire:model="name"
                                    required
                                    autocomplete="name"
                                />
                                <x-input-error class="mt-2" :messages="$errors->get('name')" />
                            </div>

                            <div>
                                <x-input-label for="email" value="ایمیل" />
                                <x-input
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full"
                                    wire:model="email"
                                    required
                                    autocomplete="email"
                                />
                                <x-input-error class="mt-2" :messages="$errors->get('email')" />
                            </div>

                            <div class="flex justify-end">
                                <x-button type="submit" class="bg-indigo-600 hover:bg-indigo-700">
                                    ذخیره تغییرات
                                </x-button>
                            </div>
                        </div>
                    </form>
                </x-card>
            </div>

            <!-- بخش تغییر رمز عبور -->
            <div class="mb-6">
                <x-card>
                    <x-slot name="header">
                        <h2 class="text-lg font-semibold text-white">تغییر رمز عبور</h2>
                    </x-slot>

                    <form wire:submit="updatePassword">
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="current_password" value="رمز عبور فعلی" />
                                <x-input
                                    id="current_password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    wire:model="current_password"
                                    required
                                    autocomplete="current-password"
                                />
                                <x-input-error class="mt-2" :messages="$errors->get('current_password')" />
                            </div>

                            <div>
                                <x-input-label for="password" value="رمز عبور جدید" />
                                <x-input
                                    id="password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    wire:model="password"
                                    required
                                    autocomplete="new-password"
                                />
                                <x-input-error class="mt-2" :messages="$errors->get('password')" />
                            </div>

                            <div>
                                <x-input-label for="password_confirmation" value="تکرار رمز عبور جدید" />
                                <x-input
                                    id="password_confirmation"
                                    type="password"
                                    class="mt-1 block w-full"
                                    wire:model="password_confirmation"
                                    required
                                    autocomplete="new-password"
                                />
                                <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
                            </div>

                            <div class="flex justify-end">
                                <x-button type="submit" class="bg-green-600 hover:bg-green-700">
                                    تغییر رمز عبور
                                </x-button>
                            </div>
                        </div>
                    </form>
                </x-card>
            </div>

            <!-- بخش حذف حساب -->
            <div>
                <x-card class="bg-red-900/20 border-red-800">
                    <x-slot name="header">
                        <h2 class="text-lg font-semibold text-red-300">حذف حساب کاربری</h2>
                    </x-slot>

                    <p class="text-sm text-gray-300 mb-4">
                        پس از حذف حساب کاربری، تمام اطلاعات شما به‌طور دائمی حذف خواهد شد.
                        لطفاً قبل از ادامه، اطلاعات مهم خود را ذخیره کنید.
                    </p>

                    <x-button
                        x-data=""
                        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
                        class="bg-red-600 hover:bg-red-700"
                    >
                        حذف حساب کاربری
                    </x-button>
                </x-card>
            </div>
        </div>
    </div>

    <!-- Modal تایید حذف -->
    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('admin.profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-100">
                آیا مطمئن هستید که می‌خواهید حساب خود را حذف کنید؟
            </h2>

            <p class="mt-1 text-sm text-gray-400">
                پس از حذف حساب، تمام اطلاعات شما به‌طور دائمی حذف خواهد شد.
                لطفاً رمز عبور خود را وارد کنید تا تأیید کنید که می‌خواهید این عمل را انجام دهید.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="رمز عبور" class="sr-only" />
                <x-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full"
                    placeholder="رمز عبور"
                />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end space-x-3 space-x-reverse">
                <x-secondary-button x-on:click="$dispatch('close')">
                    لغو
                </x-secondary-button>

                <x-danger-button class="ml-3">
                    حذف حساب
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</div>
