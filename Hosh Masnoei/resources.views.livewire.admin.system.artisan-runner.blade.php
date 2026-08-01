<div class="space-y-6">
    <x-admin.subheader title="مدیریت سیستم و ترمینال آرتیسان">
        <x-slot name="actions">
            <x-admin.buttons.secondary wire:click="clearOutput" class="flex items-center gap-2">
                <x-heroicon-o-trash class="w-4 h-4" />
                <span>پاک کردن ترمینال</span>
            </x-admin.buttons.secondary>
        </x-slot>
    </x-admin.subheader>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Command Buttons -->
        <div class="lg:col-span-1 space-y-4">
            <x-admin.card>
                <x-slot name="header">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-command-line class="w-5 h-5 text-gray-500" />
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">دستورات مجاز</h3>
                    </div>
                </x-slot>

                <div class="flex flex-col gap-3">
                    @foreach($commandList as $cmd => $info)
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
                        <span class="text-xs font-mono opacity-75 relative z-10 block" dir="ltr">{{ $cmd }}</span>

                        <!-- Loading Spinner -->
                        <div wire:loading wire:target="runCommand('{{ $cmd }}')" class="absolute inset-0 bg-black/20 flex items-center justify-center z-20">
                            <svg class="w-5 h-5 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </button>
                    @endforeach
                </div>

                <div class="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                    <p class="text-xs text-yellow-800 dark:text-yellow-300 flex items-start gap-2 leading-relaxed">
                        <x-heroicon-m-exclamation-triangle class="w-4 h-4 shrink-0 mt-0.5" />
                        <span>اجرای این دستورات مستقیماً روی سرور اعمال می‌شود. در استفاده از <strong>مایگریشن‌ها</strong> در محیط پروداکشن دقت کنید.</span>
                    </p>
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
