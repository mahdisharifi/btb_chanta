<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TotalStockTierLabelMigrationTest extends TestCase
{
    public function test_migration_renames_legacy_labels_and_preserves_other_tier_data(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->json('quantity_tiers')->nullable();
        });
        Schema::create('supplier_tier_percentages', function (Blueprint $table): void {
            $table->id();
            $table->string('tier_label')->nullable();
        });
        Schema::create('supplier_tier_discount', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->nullable();
        });

        $tiers = [
            ['name' => 'بار', 'min_quantity' => 160, 'max_quantity' => null],
            ['name' => 'کارتن', 'min_quantity' => 10, 'max_quantity' => 159],
            ['name' => 'کل موجودی', 'enabled' => false],
        ];
        DB::table('products')->insert([
            ['id' => 1, 'quantity_tiers' => json_encode($tiers)],
            ['id' => 2, 'quantity_tiers' => null],
        ]);
        DB::table('supplier_tier_percentages')->insert([
            ['tier_label' => 'بار'], ['tier_label' => 'نام اختصاصی'], ['tier_label' => null],
        ]);
        DB::table('supplier_tier_discount')->insert([
            ['label' => 'بار'], ['label' => 'نام اختصاصی'], ['label' => null],
        ]);

        $migration = require database_path('migrations/2026_11_12_120000_rename_remaining_bar_tier_labels_to_total_stock.php');
        $migration->up();
        $migration->up();

        $tiers[0]['name'] = 'کل موجودی';
        $this->assertSame($tiers, json_decode(DB::table('products')->where('id', 1)->value('quantity_tiers'), true));
        $this->assertNull(DB::table('products')->where('id', 2)->value('quantity_tiers'));
        $this->assertSame(['کل موجودی', 'نام اختصاصی', null], DB::table('supplier_tier_percentages')->orderBy('id')->pluck('tier_label')->all());
        $this->assertSame(['کل موجودی', 'نام اختصاصی', null], DB::table('supplier_tier_discount')->orderBy('id')->pluck('label')->all());
    }
}
