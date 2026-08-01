<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ۱. جدول محصولات (Products)
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasIndex('products', 'products_category_active_status_idx')) {
                $table->index(['category_id', 'is_active', 'status'], 'products_category_active_status_idx');
            }
            if (!Schema::hasIndex('products', 'products_sku_idx')) {
                $table->index('sku', 'products_sku_idx');
            }
        });

        // ۲. جدول دسته‌بندی‌ها (Categories)
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasIndex('categories', 'categories_slug_idx')) {
                $table->index('slug', 'categories_slug_idx');
            }
            if (!Schema::hasIndex('categories', 'categories_parent_active_idx')) {
                $table->index(['parent_id', 'is_active'], 'categories_parent_active_idx');
            }
        });

        // ۳. جدول قیمت محصولات (Product Prices)
        Schema::table('product_prices', function (Blueprint $table) {
            if (!Schema::hasIndex('product_prices', 'product_prices_search_idx')) {
                $table->index(['product_id', 'price_type', 'price'], 'product_prices_search_idx');
            }
        });

        // ۴. جدول واسط محصول و تامین‌کننده (Product Supplier)
        Schema::table('product_supplier', function (Blueprint $table) {
            if (!Schema::hasIndex('product_supplier', 'prod_supp_status_stock_idx')) {
                $table->index(['product_id', 'status', 'stock_quantity'], 'prod_supp_status_stock_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasIndex('products', 'products_category_active_status_idx')) {
                $table->dropIndex('products_category_active_status_idx');
            }
            if (Schema::hasIndex('products', 'products_sku_idx')) {
                $table->dropIndex('products_sku_idx');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasIndex('categories', 'categories_slug_idx')) {
                $table->dropIndex('categories_slug_idx');
            }
            if (Schema::hasIndex('categories', 'categories_parent_active_idx')) {
                $table->dropIndex('categories_parent_active_idx');
            }
        });

        Schema::table('product_prices', function (Blueprint $table) {
            if (Schema::hasIndex('product_prices', 'product_prices_search_idx')) {
                $table->dropIndex('product_prices_search_idx');
            }
        });

        Schema::table('product_supplier', function (Blueprint $table) {
            if (Schema::hasIndex('product_supplier', 'prod_supp_status_stock_idx')) {
                $table->dropIndex('prod_supp_status_stock_idx');
            }
        });
    }
};
