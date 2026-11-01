<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('parent_supplier_id')
                ->nullable()
                ->after('user_id')
                ->constrained('suppliers')
                ->restrictOnDelete();
            $table->string('branch_name', 120)->nullable()->after('parent_supplier_id');
            $table->index(['user_id', 'parent_supplier_id'], 'suppliers_branch_owner_idx');
            $table->unique(['parent_supplier_id', 'branch_name'], 'supplier_parent_branch_name_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('family_product_id')
                ->nullable()
                ->after('id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->index(['family_product_id', 'supplier_id'], 'products_family_supplier_idx');
            $table->unique(['supplier_id', 'family_product_id'], 'products_supplier_family_unique');
        });

        Schema::table('product_supplier', function (Blueprint $table) {
            $table->decimal('branch_price_percentage', 9, 6)
                ->default(0)
                ->after('tier_enabled');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_source_price_id')->nullable()->after('product_supplier_id');
            $table->unique(
                ['product_supplier_id', 'branch_source_price_id'],
                'branch_derived_price_source_unique'
            );
        });

        // Existing products remain the canonical family member. New branch copies
        // point to this ID, so current catalog URLs and view counters stay intact.
        DB::table('products')
            ->whereNull('family_product_id')
            ->orderBy('id')
            ->chunkById(500, function ($products): void {
                DB::table('products')
                    ->whereIn('id', $products->pluck('id'))
                    ->whereNull('family_product_id')
                    ->update(['family_product_id' => DB::raw('id')]);
            });

        Schema::create('supplier_branch_product_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('target_supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('source_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('destination_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['target_supplier_id', 'source_product_id'],
                'branch_transfer_target_source_unique'
            );
            $table->index(
                ['target_supplier_id', 'status', 'id'],
                'branch_transfer_target_status_idx'
            );
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_branch_product_transfers')
            && DB::table('supplier_branch_product_transfers')->exists()) {
            throw new RuntimeException('Branch transfer data exists; export or remove it before rolling back branch support.');
        }

        if (Schema::hasColumn('suppliers', 'parent_supplier_id')
            && DB::table('suppliers')->whereNotNull('parent_supplier_id')->exists()) {
            throw new RuntimeException('Branch suppliers exist; merge them before rolling back branch support.');
        }

        if (Schema::hasColumn('products', 'family_product_id')
            && DB::table('products')
                ->whereNotNull('family_product_id')
                ->whereColumn('family_product_id', '!=', 'id')
                ->exists()) {
            throw new RuntimeException('Branch product links exist; merge them before rolling back branch support.');
        }

        if (Schema::hasColumn('product_prices', 'branch_source_price_id')
            && DB::table('product_prices')->whereNotNull('branch_source_price_id')->exists()) {
            throw new RuntimeException('Branch price snapshots exist; export or remove them before rolling back branch support.');
        }

        Schema::dropIfExists('supplier_branch_product_transfers');

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropUnique('branch_derived_price_source_unique');
            $table->dropColumn('branch_source_price_id');
        });

        Schema::table('product_supplier', function (Blueprint $table) {
            $table->dropColumn('branch_price_percentage');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_family_supplier_idx');
            $table->dropUnique('products_supplier_family_unique');
            $table->dropConstrainedForeignId('family_product_id');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('suppliers_branch_owner_idx');
            $table->dropUnique('supplier_parent_branch_name_unique');
            $table->dropConstrainedForeignId('parent_supplier_id');
            $table->dropColumn('branch_name');
        });
    }
};
