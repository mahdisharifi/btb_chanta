<?php

namespace App\Livewire\Admin\System;

use Livewire\Component;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;

#[Layout('components.layouts.admin')]
class ArtisanRunner extends Component
{
    public function enableMaintenance(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        if (app()->maintenanceMode()->active()) {
            $this->output = 'حالت تعمیر از قبل فعال است.';
            return;
        }

        $secret = \Illuminate\Support\Str::random(64);

        try {
            \Illuminate\Support\Facades\Cookie::queue(
                \Illuminate\Foundation\Http\MaintenanceModeBypassCookie::create($secret)
                    ->withSecure(request()->isSecure())->withHttpOnly(true)->withSameSite('lax')
            );
            $exitCode = Artisan::call('down', ['--secret' => $secret, '--retry' => 120]);
            if ($exitCode !== 0 || ! app()->maintenanceMode()->active()) {
                throw new \RuntimeException('Maintenance activation failed.');
            }
            \Illuminate\Support\Facades\Log::notice('Maintenance enabled from admin terminal.', ['user_id' => auth()->id()]);
            $this->output = 'حالت تعمیر فعال شد. بازدیدکنندگان صفحه تعمیر را می‌بینند؛ دسترسی این مرورگر تا ۱۲ ساعت حفظ می‌شود.';
        } catch (\Throwable $e) {
            report($e);
            $this->output = 'فعال‌سازی حالت تعمیر کامل نشد. وضعیت فعلی را بررسی کنید. بازیابی از سرور: php artisan up';
        }
    }

    public function disableMaintenance(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        try {
            if (Artisan::call('up') !== 0 || app()->maintenanceMode()->active()) {
                throw new \RuntimeException('Maintenance deactivation failed.');
            }
            \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('laravel_maintenance'));
            \Illuminate\Support\Facades\Log::notice('Maintenance disabled from admin terminal.', ['user_id' => auth()->id()]);
            $this->output = 'حالت تعمیر غیرفعال شد. سایت برای کاربران در دسترس است.';
        } catch (\Throwable $e) {
            report($e);
            $this->output = 'خروج از حالت تعمیر انجام نشد. بازیابی از سرور: php artisan up';
        }
    }

    public string $output = '';
    public string $seederClass = ''; // نام کلاس seeder

    /**
     * لیست مجاز دستورات (Whitelist) برای امنیت بیشتر
     */
    public function getCommandListProperty(): array
    {
        return [
            // 🧹 دستورات پاکسازی Cache
            'optimize:clear' => ['name' => 'پاکسازی کامل کش', 'icon' => 'o-trash', 'color' => 'bg-emerald-500', 'group' => 'clear'],
            'cache:clear'    => ['name' => 'پاکسازی کش داده‌ها', 'icon' => 'o-circle-stack', 'color' => 'bg-teal-500', 'group' => 'clear'],
            'view:clear'     => ['name' => 'پاکسازی کش ویوها', 'icon' => 'o-eye-slash', 'color' => 'bg-cyan-500', 'group' => 'clear'],
            'route:clear'    => ['name' => 'پاکسازی کش روت‌ها', 'icon' => 'o-map', 'color' => 'bg-sky-500', 'group' => 'clear'],
            'config:clear'   => ['name' => 'پاکسازی کش تنظیمات', 'icon' => 'o-cog-6-tooth', 'color' => 'bg-blue-500', 'group' => 'clear'],
            'event:clear'    => ['name' => 'پاکسازی کش رویدادها', 'icon' => 'o-bolt', 'color' => 'bg-purple-500', 'group' => 'clear'],
            
            // 🚀 دستورات بهینه‌سازی Production
            'optimize'       => ['name' => 'بهینه‌سازی کامل (Cache همه چیز)', 'icon' => 'o-rocket-launch', 'color' => 'bg-green-600', 'group' => 'optimize'],
            'config:cache'   => ['name' => 'Cache تنظیمات', 'icon' => 'o-cog-6-tooth', 'color' => 'bg-blue-600', 'group' => 'optimize'],
            'route:cache'    => ['name' => 'Cache روت‌ها', 'icon' => 'o-map', 'color' => 'bg-sky-600', 'group' => 'optimize'],
            'view:cache'     => ['name' => 'Cache ویوها', 'icon' => 'o-eye', 'color' => 'bg-cyan-600', 'group' => 'optimize'],
            'event:cache'    => ['name' => 'Cache رویدادها', 'icon' => 'o-bolt', 'color' => 'bg-purple-600', 'group' => 'optimize'],
            
            // 🗄️ دستورات دیتابیس
            'migrate'        => ['name' => 'اجرای مایگریشن‌ها', 'icon' => 'o-server-stack', 'color' => 'bg-indigo-600', 'group' => 'database'],
            'migrate:status' => ['name' => 'وضعیت مایگریشن‌ها', 'icon' => 'o-information-circle', 'color' => 'bg-violet-500', 'group' => 'database'],
            
            // 📋 دستورات اطلاعاتی
            'route:list'     => ['name' => 'لیست روت‌ها', 'icon' => 'o-list-bullet', 'color' => 'bg-gray-600', 'group' => 'info'],
            'about'          => ['name' => 'اطلاعات محیط', 'icon' => 'o-information-circle', 'color' => 'bg-slate-600', 'group' => 'info'],
        ];
    }


    public function runCommand(string $command)
    {
        // بررسی دسترسی (فقط ادمین اصلی)
        if (! auth()->user()->isAdmin()) {
            abort(403, 'شما دسترسی لازم برای اجرای این دستور را ندارید.');
        }

        // جلوگیری از اجرای دستورات خارج از لیست مجاز
        if (!array_key_exists($command, $this->commandList)) {
            $this->output = "> خطای امنیتی: دستور نامعتبر است یا در لیست مجاز قرار ندارد.\n";
            return;
        }

        $this->output = "> در حال اجرای دستور: php artisan {$command} ...\n\n";

        try {
            // اجرای دستور
            Artisan::call($command);

            // دریافت خروجی
            $result = Artisan::output();
            $this->output .= $result ?: "دستور با موفقیت اجرا شد اما خروجی نداشت.\n";

        } catch (\Exception $e) {
            $this->output .= "[خطا] اجرای دستور با مشکل مواجه شد:\n" . $e->getMessage() . "\n";
        }
    }

    /**
     * اجرای Seeder سفارشی
     */
    public function runSeeder()
    {
        // بررسی دسترسی
        if (! auth()->user()->isAdmin()) {
            abort(403, 'شما دسترسی لازم برای اجرای این دستور را ندارید.');
        }

        // اعتبارسنجی ورودی
        $seederClass = trim($this->seederClass);
        
        if (empty($seederClass)) {
            $this->output = "> خطا: نام کلاس Seeder را وارد کنید.\n";
            return;
        }

        // بررسی معتبر بودن نام کلاس (فقط حروف، اعداد و backslash)
        if (!preg_match('/^[a-zA-Z0-9\\\\]+$/', $seederClass)) {
            $this->output = "> خطا: نام کلاس نامعتبر است. فقط حروف، اعداد و \\ مجاز هستند.\n";
            return;
        }

        $command = "db:seed --class={$seederClass}";
        
        $this->output = "> در حال اجرای دستور: php artisan {$command} ...\n\n";

        try {
            Artisan::call($command);
            $result = Artisan::output();
            $this->output .= $result ?: "Seeder با موفقیت اجرا شد.\n";
            
            // پاک کردن فیلد بعد از اجرای موفق
            $this->seederClass = '';
        } catch (\Exception $e) {
            $this->output .= "[خطا] اجرای Seeder با مشکل مواجه شد:\n" . $e->getMessage() . "\n";
        }
    }

    public function clearOutput()
    {
        $this->output = '';
    }

    public function render()
    {
        return view('livewire.admin.system.artisan-runner', [
            'maintenanceActive' => app()->maintenanceMode()->active(),
            'commandList' => $this->commandList,
        ]);
    }
}
