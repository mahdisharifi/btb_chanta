<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_completion_tasks')
            ->where('task_type', 'correction')
            ->whereNull('full_rate_approved_by')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('product_completion_commissions')
                    ->whereColumn(
                        'product_completion_commissions.task_id',
                        'product_completion_tasks.id'
                    );
            })
            ->update(['commission_rate_percentage' => '30.00']);
    }

    public function down(): void
    {
        DB::table('product_completion_tasks')
            ->where('task_type', 'correction')
            ->whereNull('full_rate_approved_by')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('product_completion_commissions')
                    ->whereColumn(
                        'product_completion_commissions.task_id',
                        'product_completion_tasks.id'
                    );
            })
            ->update(['commission_rate_percentage' => '100.00']);
    }
};
