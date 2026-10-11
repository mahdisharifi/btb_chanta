<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_category', function (Blueprint $table): void {
            $table->unsignedInteger('display_order')->default(0)->after('category_id');
            $table->boolean('is_required')->default(false)->after('display_order');
            $table->index(['category_id', 'display_order'], 'attribute_category_category_order_index');
        });

        $rows = DB::table('attribute_category')
            ->select(['category_id', 'attribute_id'])
            ->orderBy('category_id')
            ->orderBy('attribute_id')
            ->get()
            ->groupBy('category_id');

        foreach ($rows as $categoryId => $attributes) {
            foreach ($attributes->values() as $index => $attribute) {
                DB::table('attribute_category')
                    ->where('category_id', $categoryId)
                    ->where('attribute_id', $attribute->attribute_id)
                    ->update(['display_order' => $index + 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('attribute_category', function (Blueprint $table): void {
            $table->dropIndex('attribute_category_category_order_index');
            $table->dropColumn(['display_order', 'is_required']);
        });
    }
};
