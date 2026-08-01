<div>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">مدیریت پلن‌های اشتراک (VIP)</h2>
        <button wire:click="create" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition">
            <x-heroicon-o-plus class="w-5 h-5 ml-2" />
            افزودن پلن جدید
        </button>
    </div>

    @if (session()->has('message'))
    <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
        {{ session('message') }}
    </div>
    @endif

    <!-- جدول نمایش پلن‌ها -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="w-full text-sm text-right text-gray-500">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3">عنوان پلن</th>
                <th scope="col" class="px-6 py-3">مدت زمان (روز)</th>
                <th scope="col" class="px-6 py-3">مبلغ (تومان)</th>
                <th scope="col" class="px-6 py-3">وضعیت</th>
                <th scope="col" class="px-6 py-3">عملیات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($plans as $plan)
            <tr class="bg-white border-b hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                    {{ $plan->title }}
                </td>
                <td class="px-6 py-4">
                    {{ $plan->duration_days }} روز
                </td>
                <td class="px-6 py-4">
                    {{ number_format($plan->price) }}
                </td>
                <td class="px-6 py-4">
                    <button wire:click="toggleActive({{ $plan->id }})" class="px-3 py-1 text-xs rounded-full {{ $plan->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $plan->is_active ? 'فعال' : 'غیرفعال' }}
                    </button>
                </td>
                <td class="px-6 py-4 flex items-center gap-3">
                    <button wire:click="edit({{ $plan->id }})" class="text-blue-600 hover:text-blue-900" title="ویرایش">
                        <x-heroicon-o-pencil class="w-5 h-5" />
                    </button>
                    <button wire:click="delete({{ $plan->id }})" onclick="confirm('آیا از حذف این پلن مطمئن هستید؟') || event.stopImmediatePropagation()" class="text-red-600 hover:text-red-900" title="حذف">
                        <x-heroicon-o-trash class="w-5 h-5" />
                    </button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                    هیچ پلنی یافت نشد.
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-4">
            {{ $plans->links() }}
        </div>
    </div>

    <!-- مودال ایجاد / ویرایش -->
    @if($isOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center overflow-auto bg-black bg-opacity-50">
        <div class="relative bg-white rounded-lg shadow-lg w-full max-w-lg mx-4">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-xl font-semibold text-gray-900">
                    {{ $isEditMode ? 'ویرایش پلن' : 'افزودن پلن جدید' }}
                </h3>
                <button wire:click="closeModal" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">عنوان پلن <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="title" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    @error('title') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900">مدت زمان (روز) <span class="text-red-500">*</span></label>
                        <input type="number" wire:model="duration_days" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        @error('duration_days') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900">مبلغ (تومان) <span class="text-red-500">*</span></label>
                        <input type="number" wire:model="price" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        @error('price') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="mb-4">
                    <x-input-label for="target_role" value="جامعه هدف پلن" />
                    <select id="target_role" wire:model="target_role" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="all">عمومی (همه)</option>
                        <option value="supplier">تامین‌کننده (Supplier)</option>
                        <option value="retailer_premium">خرده‌فروش ویژه (Retailer Premium)</option>
                        <option value="retailer_basic">خرده‌فروش پایه (Retailer Basic)</option>
                        <option value="end_user">مشتری نهایی (End User)</option>
                    </select>
                    <x-input-error :messages="$errors->get('target_role')" class="mt-2" />
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">توضیحات پلن</label>
                    <textarea wire:model="description" rows="3" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500"></textarea>
                    @error('description') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center">
                    <input type="checkbox" wire:model="is_active" id="is_active" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                    <label for="is_active" class="ml-2 mr-2 text-sm font-medium text-gray-900">پلن فعال باشد</label>
                </div>
            </div>

            <div class="flex items-center p-4 border-t border-gray-200 rounded-b">
                <button wire:click="store" type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">ذخیره اطلاعات</button>
                <button wire:click="closeModal" type="button" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 mr-3">انصراف</button>
            </div>
        </div>
    </div>
    @endif
</div>
