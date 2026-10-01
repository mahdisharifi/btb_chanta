<div class="space-y-6">
    <x-admin.subheader title="مدیریت سیستم و ترمینال آرتیسان">
        <x-slot name="actions">
            <x-admin.buttons.secondary wire:click="clearOutput" class="flex items-center gap-2">
                <x-heroicon-o-trash class="w-4 h-4" />
                <span>پاک کردن ترمینال</span>
            </x-admin.buttons.secondary>
        </x-slot>
    </x-admin.subheader>

    {{-- Debug: نمایش تعداد دستورات --}}
    @if(app()->environment('local') || (auth()->user() && auth()->user()->isAdmin()))
    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm">
        <strong>Debug Info:</strong> تعداد دستورات: {{ count($commandList ?? []) }} | 
        دستورات با group: {{ collect($commandList ?? [])->filter(fn($info) => isset($info['group']))->count() }}
        @if(count($commandList ?? []) === 0)
            <div class="mt-2 text-red-600 font-bold">⚠️ هیچ دستوری در $commandList نیست! فایل PHP را بررسی کنید.</div>
        @endif
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Command Buttons -->
        <div class="lg:col-span-1 space-y-4">
            <!-- 🧹 دستورات پاکسازی -->
            <x-admin.card>
                <x-slot name="header">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-trash class="w-5 h-5 text-emerald-600" />
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">پاکسازی Cache</h3>
                    </div>
                </x-slot>

                <div class="flex flex-col gap-2">
                    @foreach($commandList as $cmd => $info)
                        @if(isset($info['group']) && $info['group'] === 'clear')
                        <button
                            wire:click="runCommand('{{ $cmd }}')"
                            wire:loading.attr="disabled"
                            wire:target="runCommand('{{ $cmd }}')"
                            class="relative flex items-center justify-between w-full p-3 overflow-hidden text-white transition-all rounded-lg shadow hover:shadow-md active:scale-[0.98] {{ $info['color'] }} hover:brightness-110 disabled:opacity-70 disabled:cursor-not-allowed"
                        >
                            <div class="flex items-center gap-3 relative z-10">
                                @svg('heroicon-'.$info['icon'], 'w-5 h-5')
                                <span class="font-medium text-sm">{{ $info['name'] }}</span>
                            </div>
                            <span class="text-xs font-mono opacity-75 relative z-10 hidden sm:block" dir="ltr">{{ $cmd }}</span>

                            <!-- Loading Spinner -->
                            <div wire:loading wire:target="runCommand('{{ $cmd }}')" class="absolute inset-0 bg-black/20 flex items-center justify-center z-20">
                                <svg class="w-5 h-5 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </button>
                        @endif
                    @endforeach
                </div>
            </x-admin.card>

            <!-- 🚀 دستورات بهینه‌سازی -->
            <x-admin.card>
                <x-slot name="header">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-rocket-launch class="w-5 h-5 text-green-600" />
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">بهینه‌سازی Production</h3>
                    </div>
                </x-slot>

                <div class="flex flex-col gap-2">
                    @foreach($commandList as $cmd => $info)
                        @if(isset($info['group']) && $info['group'] === 'optimize')
                        <button
                            wire:click="runCommand('{{ $cmd }}')"
                            wire:loading.attr="disabled"
                            wire:target="runCommand('{{ $cmd }}')"
                            class="relative flex items-center justify-between w-full p-3 overflow-hidden text-white transition-all rounded-lg shadow hover:shadow-md active:scale-[0.98] {{ $info['color'] }} hover:brightness-110 disabled:opacity-70 disabled:cursor-not-allowed"
                        >
                            <div class="flex items-center gap-3 relative z-10">
                                @svg('heroicon-'.$info['icon'], 'w-5 h-5')
                                <span class="font-medium text-sm">{{ $info['name'] }}</span>
                            </div>
                            <span class="text-xs font-mono opacity-75 relative z-10 hidden sm:block" dir="ltr">{{ $cmd }}</span>

                            <!-- Loading Spinner -->
                            <div wire:loading wire:target="runCommand('{{ $cmd }}')" class="absolute inset-0 bg-black/20 flex items-center justify-center z-20">
                                <svg class="w-5 h-5 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </button>
                        @endif
                    @endforeach
                </div>

                <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <p class="text-xs text-blue-800 dark:text-blue-300 flex items-start gap-2 leading-relaxed">
                        <x-heroicon-o-information-circle class="w-4 h-4 shrink-0 mt-0.5" />
                        <span>برای <strong>Production</strong>: ابتدا <code class="px-1 py-0.5 bg-blue-200 dark:bg-blue-800 rounded">optimize</code> را اجرا کنید.</span>
                    </p>
                </div>
            </x-admin.card>

            <!-- 🗄️ دستورات دیتابیس -->
            <x-admin.card>
                <x-slot name="header">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-server-stack class="w-5 h-5 text-indigo-600" />
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">دیتابیس</h3>
                    </div>
                </x-slot>

                <div class="flex flex-col gap-2">
                    @foreach($commandList as $cmd => $info)
                        @if(isset($info['group']) && $info['group'] === 'database')
                        <button
                            wire:click="runCommand('{{ $cmd }}')"
                            wire:loading.attr="disabled"
                            wire:target="runCommand('{{ $cmd }}')"
                            class="relative flex items-center justify-between w-full p-3 overflow-hidden text-white transition-all rounded-lg shadow hover:shadow-md active:scale-[0.98] {{ $info['color'] }} hover:brightness-110 disabled:opacity-70 disabled:cursor-not-allowed"
                        >
                            <div class="flex items-center gap-3 relative z-10">
                                @svg('heroicon-'.$info['icon'], 'w-5 h-5')
                                <span class="font-medium text-sm">{{ $info['name'] }}</span>
                            </div>
                            <span class="text-xs font-mono opacity-75 relative z-10 hidden sm:block" dir="ltr">{{ $cmd }}</span>

                            <!-- Loading Spinner -->
                            <div wire:loading wire:target="runCommand('{{ $cmd }}')" class="absolute inset-0 bg-black/20 flex items-center justify-center z-20">
                                <svg class="w-5 h-5 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </button>
                        @endif
                    @endforeach
                </div>

                <div class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                    <p class="text-xs text-yellow-800 dark:text-yellow-300 flex items-start gap-2 leading-relaxed">
                        <x-heroicon-m-exclamation-triangle class="w-4 h-4 shrink-0 mt-0.5" />
                        <span><strong>توجه:</strong> در Production با احتیاط از مایگریشن استفاده کنید.</span>
                    </p>
                </div>
            </x-admin.card>

            <!-- 🌱 اجرای Seeder سفارشی -->
            <x-admin.card>
                <x-slot name="header">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-beaker class="w-5 h-5 text-amber-600" />
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">اجرای Seeder</h3>
                    </div>
                </x-slot>

                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            نام کلاس Seeder
                        </label>
                        <input
                            type="text"
                            wire:model="seederClass"
                            placeholder="مثال: MainSliderSeeder"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 dark:bg-gray-700 dark:text-white text-sm font-mono"
                            dir="ltr"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            فقط نام کلاس را وارد کنید (بدون مسیر)
                        </p>
                    </div>

                    <button
                        wire:click="runSeeder"
                        wire:loading.attr="disabled"
                        wire:target="runSeeder"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-lg shadow transition-all disabled:opacity-70 disabled:cursor-not-allowed"
                    >
                        <x-heroicon-o-play class="w-4 h-4" />
                        <span>اجرای Seeder</span>
                        
                        <!-- Loading Spinner -->
                        <svg wire:loading wire:target="runSeeder" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>

                    <div class="p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg">
                        <p class="text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2 leading-relaxed">
                            <x-heroicon-o-light-bulb class="w-4 h-4 shrink-0 mt-0.5" />
                            <span>
                                <strong>نمونه:</strong> MainSliderSeeder, UserSeeder, ProductSeeder
                            </span>
                        </p>
                    </div>
                </div>
            </x-admin.card>

            <!-- 📋 دستورات اطلاعاتی -->
            <x-admin.card>
                <x-slot name="header">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-information-circle class="w-5 h-5 text-gray-600" />
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">اطلاعات</h3>
                    </div>
                </x-slot>

                <div class="flex flex-col gap-2">
                    @foreach($commandList as $cmd => $info)
                        @if(isset($info['group']) && $info['group'] === 'info')
                        <button
                            wire:click="runCommand('{{ $cmd }}')"
                            wire:loading.attr="disabled"
                            wire:target="runCommand('{{ $cmd }}')"
                            class="relative flex items-center justify-between w-full p-3 overflow-hidden text-white transition-all rounded-lg shadow hover:shadow-md active:scale-[0.98] {{ $info['color'] }} hover:brightness-110 disabled:opacity-70 disabled:cursor-not-allowed"
                        >
                            <div class="flex items-center gap-3 relative z-10">
                                @svg('heroicon-'.$info['icon'], 'w-5 h-5')
                                <span class="font-medium text-sm">{{ $info['name'] }}</span>
                            </div>
                            <span class="text-xs font-mono opacity-75 relative z-10 hidden sm:block" dir="ltr">{{ $cmd }}</span>

                            <!-- Loading Spinner -->
                            <div wire:loading wire:target="runCommand('{{ $cmd }}')" class="absolute inset-0 bg-black/20 flex items-center justify-center z-20">
                                <svg class="w-5 h-5 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </button>
                        @endif
                    @endforeach
                </div>
            </x-admin.card>
        </div>

        <!-- Terminal Output -->
        <div class="lg:col-span-2">
            <x-admin.card class="h-full flex flex-col">
                <x-slot name="header">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-computer-desktop class="w-5 h-5 text-gray-500" />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-200">خروجی ترمینال</h3>
                        </div>
                        <div class="flex gap-1">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                            <div class="w-3 h-3 rounded-full bg-green-500"></div>
                        </div>
                    </div>
                </x-slot>

                <div class="bg-gray-900 rounded-b-lg flex-1 min-h-[400px] p-4 font-mono text-sm overflow-x-auto shadow-inner relative" dir="ltr">
                    <div wire:loading wire:target="runCommand" class="absolute top-4 right-4 text-gray-400">
                        <span class="animate-pulse">Processing...</span>
                    </div>

                    @if(empty($output))
                    <div class="text-gray-500 select-none">
                        <p>Waiting for command execution...</p>
                        <p class="mt-2 text-xs">System ready.</p>
                    </div>
                    @else
                    <pre class="text-green-400 whitespace-pre-wrap break-words leading-relaxed">{!! htmlspecialchars($output) !!}</pre>
                    @endif
                </div>
            </x-admin.card>
        </div>
    </div>
</div>
