<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // تغییر enum type برای اضافه کردن مقدار 'admin'
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE suppliers MODIFY COLUMN type ENUM('supplier', 'retailer', 'admin') DEFAULT 'supplier'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // برگرداندن به حالت قبلی (فقط supplier و retailer)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE suppliers MODIFY COLUMN type ENUM('supplier', 'retailer') DEFAULT 'supplier'");
        }
    }
};
