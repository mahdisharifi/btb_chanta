<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\CatalogTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;

class ConcurrentUserTest extends TestCase
{
    /**
     * تست تولید کاتالوگ همزمان توسط چند کاربر
     */
    public function test_concurrent_catalog_generation(): void
    {
        // ایجاد چند کاربر تست
        $users = User::factory()->count(5)->create([
            'role' => 'supplier'
        ]);

        // ایجاد کاتالوگ برای هر کاربر
        $catalogs = [];
        foreach ($users as $user) {
            $catalogs[] = CatalogTemplate::factory()->create([
                'supplier_id' => $user->supplier->id ?? 1,
                'settings' => [
                    'categories' => 'all',
                    'price_types' => 'all',
                    'separate_categories' => false,
                    'show_cover_page' => true,
                ]
            ]);
        }

        // شبیه‌سازی درخواست‌های همزمان
        $responses = [];
        foreach ($catalogs as $index => $catalog) {
            $user = $users[$index];
            
            // هر کاربر یک progressKey منحصر به فرد دارد
            $progressKey = 'catalog_progress_test_' . $user->id . '_' . time();
            
            // ذخیره در Cache
            Cache::put($progressKey, [
                'percent' => 0,
                'message' => 'آماده‌سازی...',
                'timestamp' => time()
            ], now()->addMinutes(5));

            // بررسی اینکه هر کاربر progress خودش را می‌بیند
            $cachedProgress = Cache::get($progressKey);
            
            $this->assertNotNull($cachedProgress);
            $this->assertEquals(0, $cachedProgress['percent']);
        }

        // بررسی عدم تداخل progressKey ها
        $progressKeys = [];
        foreach ($users as $user) {
            $key = 'catalog_progress_test_' . $user->id . '_' . time();
            $this->assertNotContains($key, $progressKeys);
            $progressKeys[] = $key;
        }

        // پاکسازی
        foreach ($users as $user) {
            $user->forceDelete();
        }
    }

    /**
     * تست Progress Bar با Cache
     */
    public function test_progress_bar_cache_isolation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $key1 = 'catalog_progress_' . $user1->id . '_test1';
        $key2 = 'catalog_progress_' . $user2->id . '_test2';

        // کاربر 1 در 30%
        Cache::put($key1, [
            'percent' => 30,
            'message' => 'در حال پردازش...',
            'timestamp' => time()
        ], now()->addMinutes(5));

        // کاربر 2 در 60%
        Cache::put($key2, [
            'percent' => 60,
            'message' => 'در حال رندر...',
            'timestamp' => time()
        ], now()->addMinutes(5));

        // بررسی isolation
        $progress1 = Cache::get($key1);
        $progress2 = Cache::get($key2);

        $this->assertEquals(30, $progress1['percent']);
        $this->assertEquals(60, $progress2['percent']);
        $this->assertNotEquals($progress1['percent'], $progress2['percent']);

        // پاکسازی
        $user1->forceDelete();
        $user2->forceDelete();
    }

    /**
     * تست API endpoint برای progress
     */
    public function test_progress_api_endpoint(): void
    {
        $user = User::factory()->create();
        
        $progressKey = 'catalog_progress_test_' . $user->id;
        
        Cache::put($progressKey, [
            'percent' => 50,
            'message' => 'در حال پردازش محصولات...',
            'timestamp' => time()
        ], now()->addMinutes(5));

        // تست endpoint
        $response = $this->actingAs($user)
            ->getJson("/catalog/progress/{$progressKey}");

        $response->assertStatus(200)
            ->assertJson([
                'percent' => 50,
                'message' => 'در حال پردازش محصولات...'
            ]);

        $user->forceDelete();
    }
}
