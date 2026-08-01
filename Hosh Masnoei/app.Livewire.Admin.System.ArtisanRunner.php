<?php

namespace App\Livewire\Admin\System;

use Livewire\Component;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;

#[Layout('components.layouts.admin')]
class ArtisanRunner extends Component
{
    public string $output = '';

    // لیست مجاز دستورات (Whitelist) برای امنیت بیشتر
    public array $commandList = [
        'optimize:clear' => ['name' => 'پاکسازی کامل کش', 'icon' => 'o-trash', 'color' => 'bg-emerald-500'],
        'cache:clear'    => ['name' => 'پاکسازی کش داده‌ها', 'icon' => 'o-circle-stack', 'color' => 'bg-teal-500'],
        'view:clear'     => ['name' => 'پاکسازی کش ویوها', 'icon' => 'o-eye-slash', 'color' => 'bg-cyan-500'],
        'route:clear'    => ['name' => 'پاکسازی کش روت‌ها', 'icon' => 'o-map', 'color' => 'bg-sky-500'],
        'config:clear'   => ['name' => 'پاکسازی کش تنظیمات', 'icon' => 'o-cog-6-tooth', 'color' => 'bg-blue-500'], // تغییر به cog-6-tooth
        'migrate'        => ['name' => 'اجرای مایگریشن‌ها', 'icon' => 'o-server-stack', 'color' => 'bg-indigo-600'], // تغییر از database به server-stack
        'migrate:status' => ['name' => 'وضعیت مایگریشن‌ها', 'icon' => 'o-information-circle', 'color' => 'bg-violet-500'],
    ];


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

    public function clearOutput()
    {
        $this->output = '';
    }

    public function render()
    {
        return view('livewire.admin.system.artisan-runner');
    }
}
