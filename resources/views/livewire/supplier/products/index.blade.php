<div>

    <a href="{{ route('supplier.products.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-sm">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        پیشنهاد کالای جدید
    </a>
    <!-- نمایش پیام موفقیت -->
    @if (session()->has('success'))
    <div class="p-4 mb-6 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400" role="alert">
        {{ session('success') }}
    </div>
    @endif

    <!-- آمار محصولات -->
    <div class="grid grid-cols-1 gap-6 mb-6 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card title="کل محصولات" :value="$this->stats['total']" icon="heroicon-o-cube" color="blue" />
        <x-admin.stat-card title="فعال" :value="$this->stats['active']" icon="heroicon-o-check-circle" color="green" />
        <x-admin.stat-card title="در انتظار بررسی" :value="$this->stats['pending']" icon="heroicon-o-clock" color="yellow" />
        <x-admin.stat-card title="پیش‌نویس" :value="$this->stats['draft']" icon="heroicon-o-document" color="gray" />
    </div>

    <!-- فیلترها و جستجو -->
    <x-admin.card class="mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <!-- جستجو -->
            <div>
                <x-input-label for="search" value="جستجو" />
                <x-text-input id="search" wire:model.live.debounce.300ms="search" type="text" class="block w-full mt-1" placeholder="عنوان یا کد SKU..." />
            </div>

            <!-- فیلتر دسته‌بندی -->
            <div>
                <x-input-label for="categoryId" value="دسته‌بندی" />
                <select id="categoryId" wire:model.live="categoryId" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                    <option value="">همه دسته‌ها</option>
                    @foreach($this->categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- فیلتر وضعیت -->
            <div>
                <x-input-label for="status" value="وضعیت" />
                <select id="status" wire:model.live="status" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="active">فعال</option>
                    <option value="pending_review">در انتظار بررسی</option>
                    <option value="draft">پیش‌نویس</option>
                    <option value="rejected">رد شده</option>
                </select>
            </div>

            <!-- مرتب‌سازی -->
            <div>
                <x-input-label for="sortBy" value="مرتب‌سازی" />
                <select id="sortBy" wire:model.live="sortBy" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                    <option value="latest">جدیدترین</option>
                    <option value="oldest">قدیمی‌ترین</option>
                    <option value="title">حروف الفبا</option>
                    <option value="views">پربازدیدترین</option>
                </select>
            </div>
        </div>
    </x-admin.card>

    <!-- جدول محصولات -->
    <x-admin.card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    <th scope="col" class="px-6 py-3">محصول</th>
                    <th scope="col" class="px-6 py-3">کد (SKU)</th>
                    <th scope="col" class="px-6 py-3">وضعیت</th>
                    <th scope="col" class="px-6 py-3 text-left">عملیات</th>
                </tr>
                </thead>
                <tbody>
                @forelse($this->products as $product)
                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <td class="flex items-center px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                        @if($product->galleries->isNotEmpty())
                        <img src="{{ Storage::url($product->galleries->first()->file_path) }}" alt="{{ $product->title }}" class="object-cover w-10 h-10 ml-3 rounded-lg">
                        @else
                        <div class="flex items-center justify-center w-10 h-10 ml-3 bg-gray-100 rounded-lg dark:bg-gray-700">
                            <x-icon name="heroicon-o-photo" class="w-6 h-6 text-gray-400" />
                        </div>
                        @endif
                        <span>{{ $product->title }}</span>
                    </td>

                    <td class="px-6 py-4">
                        {{ $product->sku }}
                    </td>
                    <td class="px-6 py-4">
                        <x-admin.status-badge :status="$product->status" />
                    </td>

                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right">
                        <div class="flex items-center justify-end gap-3">

                            <!-- دکمه ویرایش مشخصات محصول (فقط برای محصولات پیش‌نویس) -->
                            @if($product->status === 'draft' || $product->status === \App\Enums\ProductStatus::DRAFT)
                            <a href="{{ route('supplier.products.edit', $product->id) }}" class="text-blue-500 hover:text-blue-700" title="ویرایش مشخصات">
                                <x-icon name="heroicon-o-pencil-square" class="w-5 h-5" />
                            </a>
                            @else
                            <button type="button" disabled class="text-gray-300 cursor-not-allowed" title="فقط محصولات پیش‌نویس قابل ویرایش هستند">
                                <x-icon name="heroicon-o-pencil-square" class="w-5 h-5" />
                            </button>
                            @endif

                            <!-- دکمه حذف -->
                            @php
                            // بررسی وضعیت پیش‌نویس
                            $isDraft = ($product->status === 'draft' || $product->status === \App\Enums\ProductStatus::DRAFT);

                            // استفاده از auth()->user()->supplier->id به جای supplierProfile
                            $hasOtherSuppliers = $product->suppliers->where('id', '!=', auth()->user()->supplier->id)->isNotEmpty();

                            $canDelete = $isDraft && !$hasOtherSuppliers;
                            @endphp


                            @if($canDelete)
                            <button type="button"
                                    wire:click="deleteProduct({{ $product->id }})"
                                    wire:confirm="آیا از حذف این محصول اطمینان دارید؟"
                                    class="text-red-500 hover:text-red-700 transition-colors"
                                    title="حذف محصول">
                                <x-icon name="heroicon-o-trash" class="w-5 h-5" />
                            </button>
                            @else
                            <button type="button"
                                    disabled
                                    class="text-gray-300 cursor-not-allowed"
                                    title="{{ !$isDraft ? 'فقط محصولات پیش‌نویس قابل حذف هستند' : 'این محصول توسط تامین‌کنندگان دیگر قیمت‌گذاری شده و قابل حذف نیست' }}">
                                <x-icon name="heroicon-o-trash" class="w-5 h-5" />
                            </button>
                            @endif

                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                        محصولی با این مشخصات یافت نشد.
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <!-- صفحه‌بندی -->
        <div class="pt-4 mt-4 border-t dark:border-gray-700">
            {{ $this->products->links() }}
        </div>
    </x-admin.card>
</div>
