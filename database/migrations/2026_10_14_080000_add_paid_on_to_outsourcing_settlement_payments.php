<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outsourcing_settlement_payments', function (Blueprint $table): void {
            // Existing receipts have no confirmed actual payment date.
            $table->date('paid_on')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('outsourcing_settlement_payments', function (Blueprint $table): void {
            $table->dropIndex(['paid_on']);
            $table->dropColumn('paid_on');
        });
    }
};
