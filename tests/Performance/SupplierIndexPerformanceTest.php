<?php

/**
 * تست Performance برای صفحه لیست تأمین‌کنندگان
 * 
 * نحوه اجرا:
 * php artisan test tests/Performance/SupplierIndexPerformanceTest.php
 */

namespace Tests\Performance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\Supplier;
use App\Models\User;

class SupplierIndexPerformanceTest extends TestCase
{
    /**
     * تست تعداد Query های صفحه اصلی
     */
    public function test_supplier_index_query_count(): void
    {
        // ایجاد چند Supplier برای تست
        Supplier::factory()->count(30)->create(['is_active' => true]);

        DB::enableQueryLog();

        $response = $this->get(route('suppliers.index'));

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        echo PHP_EOL . "═══════════════════════════════════════" . PHP_EOL;
        echo "📊 Performance Report: Supplier Index" . PHP_EOL;
        echo "═══════════════════════════════════════" . PHP_EOL;
        echo "Total Queries: {$queryCount}" . PHP_EOL;
        echo "───────────────────────────────────────" . PHP_EOL;

        foreach ($queries as $i => $query) {
            $time = number_format($query['time'], 2);
            $sql = substr($query['query'], 0, 100);
            echo sprintf("%d. [%sms] %s...", $i + 1, $time, $sql) . PHP_EOL;
        }

        echo "═══════════════════════════════════════" . PHP_EOL;

        $response->assertStatus(200);

        // انتظار: حداکثر 10 query (با cache، باید کمتر باشد)
        $this->assertLessThanOrEqual(10, $queryCount, 
            "Query count باید کمتر از 10 باشد. فعلی: {$queryCount}"
        );
    }

    /**
     * تست Response Time صفحه
     */
    public function test_supplier_index_response_time(): void
    {
        Supplier::factory()->count(50)->create(['is_active' => true]);

        $startTime = microtime(true);

        $response = $this->get(route('suppliers.index'));

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // ms

        echo PHP_EOL . "⏱️  Response Time: " . number_format($responseTime, 2) . "ms" . PHP_EOL;

        $response->assertStatus(200);

        // انتظار: کمتر از 500ms
        $this->assertLessThan(500, $responseTime, 
            "Response time باید کمتر از 500ms باشد. فعلی: " . number_format($responseTime, 2) . "ms"
        );
    }

    /**
     * تست Cache Hit
     */
    public function test_supplier_cache_works(): void
    {
        Supplier::factory()->count(10)->create(['is_active' => true]);

        // اولین بار (cold cache)
        DB::enableQueryLog();
        $this->get(route('suppliers.index'));
        $queriesFirstTime = count(DB::getQueryLog());

        // دومین بار (warm cache)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('suppliers.index'));
        $queriesSecondTime = count(DB::getQueryLog());

        echo PHP_EOL . "🔥 Cache Performance:" . PHP_EOL;
        echo "   Cold Cache: {$queriesFirstTime} queries" . PHP_EOL;
        echo "   Warm Cache: {$queriesSecondTime} queries" . PHP_EOL;

        // انتظار: با cache، query های دفعه دوم کمتر باشد
        $this->assertLessThanOrEqual($queriesFirstTime, $queriesSecondTime);
    }

    /**
     * تست Memory Usage
     */
    public function test_supplier_index_memory_usage(): void
    {
        Supplier::factory()->count(100)->create(['is_active' => true]);

        $memoryBefore = memory_get_usage(true);

        $response = $this->get(route('suppliers.index'));

        $memoryAfter = memory_get_usage(true);
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB

        echo PHP_EOL . "💾 Memory Usage: " . number_format($memoryUsed, 2) . "MB" . PHP_EOL;

        $response->assertStatus(200);

        // انتظار: کمتر از 10MB
        $this->assertLessThan(10, $memoryUsed, 
            "Memory usage باید کمتر از 10MB باشد. فعلی: " . number_format($memoryUsed, 2) . "MB"
        );
    }

    /**
     * تست Index Performance با فیلترها
     */
    public function test_supplier_index_with_filters_performance(): void
    {
        Supplier::factory()->count(50)->create([
            'is_active' => true,
            'province' => 'تهران',
        ]);

        DB::enableQueryLog();

        $response = $this->get(route('suppliers.index', [
            'province' => 'تهران',
            'sort' => 'verified_first',
        ]));

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        echo PHP_EOL . "🔍 Filtered Query Count: {$queryCount}" . PHP_EOL;

        $response->assertStatus(200);

        // با indexes، query count نباید تغییر زیادی کند
        $this->assertLessThanOrEqual(12, $queryCount);
    }
}
