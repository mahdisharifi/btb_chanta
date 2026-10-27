<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckFFmpeg extends Command
{
    protected $signature = 'check:ffmpeg';
    protected $description = 'بررسی وجود FFmpeg روی سرور';

    public function handle(): int
    {
        $this->info('=== بررسی FFmpeg ===');
        $this->newLine();

        // 1. بررسی با which/where
        $this->line('🔍 جستجوی FFmpeg...');
        
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $command = $isWindows ? 'where ffmpeg' : 'which ffmpeg';
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $this->info('✅ FFmpeg پیدا شد!');
            $this->line('   مسیر: ' . implode(', ', $output));
        } else {
            $this->error('❌ FFmpeg پیدا نشد!');
            $this->newLine();
            $this->warn('راهنمای نصب:');
            
            if ($isWindows) {
                $this->line('   ویندوز: choco install ffmpeg');
                $this->line('   یا دانلود از: https://www.gyan.dev/ffmpeg/builds/');
            } else {
                $this->line('   Ubuntu: sudo apt install ffmpeg');
                $this->line('   CentOS: sudo yum install ffmpeg');
            }
            
            return self::FAILURE;
        }

        $this->newLine();

        // 2. بررسی نسخه
        $this->line('📋 دریافت اطلاعات نسخه...');
        exec('ffmpeg -version 2>&1', $versionOutput, $versionCode);
        
        if ($versionCode === 0 && !empty($versionOutput)) {
            $this->info('✅ نسخه FFmpeg:');
            // نمایش 3 خط اول
            foreach (array_slice($versionOutput, 0, 3) as $line) {
                $this->line('   ' . $line);
            }
        }

        $this->newLine();

        // 3. تست عملکرد
        $this->line('🧪 تست عملکرد...');
        
        // ساخت یک تصویر تستی
        $testVideoPath = storage_path('app/test-video.txt');
        file_put_contents($testVideoPath, 'test');
        
        $canExecute = is_executable($output[0] ?? '');
        
        if ($canExecute) {
            $this->info('✅ FFmpeg قابل اجراست');
        } else {
            $this->warn('⚠️  FFmpeg ممکن است مشکل دسترسی داشته باشد');
        }

        $this->newLine();

        // 4. بررسی پکیج PHP
        $this->line('📦 بررسی پکیج PHP...');
        
        $composerLock = json_decode(file_get_contents(base_path('composer.lock')), true);
        $hasPhpFFmpeg = collect($composerLock['packages'] ?? [])
            ->contains(fn($pkg) => $pkg['name'] === 'php-ffmpeg/php-ffmpeg');
        
        if ($hasPhpFFmpeg) {
            $this->info('✅ پکیج php-ffmpeg/php-ffmpeg نصب شده');
        } else {
            $this->warn('⚠️  پکیج php-ffmpeg/php-ffmpeg نصب نیست');
            $this->line('   نصب: composer require php-ffmpeg/php-ffmpeg');
        }

        $this->newLine();

        // 5. نتیجه‌گیری
        $this->info('=== خلاصه ===');
        
        if ($returnCode === 0) {
            $this->info('✅ FFmpeg آماده استفاده است!');
            $this->line('   می‌توانید از auto-generate thumbnail استفاده کنید.');
        } else {
            $this->error('❌ FFmpeg در دسترس نیست!');
            $this->line('   از آپلود دستی poster برای ویدئوها استفاده کنید.');
        }

        return self::SUCCESS;
    }
}
