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
        Schema::table('commission_rules', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->index(['task_type', 'is_default', 'is_active'], 'commission_rules_default_lookup_index');
        });

        $now = now();
        $rules = DB::table('commission_rules')
            ->where('is_active', true)
            ->where('task_type', '!=', 'seo')
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $now))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $now))
            ->orderByDesc('id')
            ->get(['id', 'task_type']);

        $defaultedTaskTypes = [];
        foreach ($rules as $rule) {
            if (isset($defaultedTaskTypes[$rule->task_type])) {
                continue;
            }

            DB::table('commission_rules')->where('id', $rule->id)->update(['is_default' => true]);
            $defaultedTaskTypes[$rule->task_type] = true;
        }
    }

    public function down(): void
    {
        Schema::table('commission_rules', function (Blueprint $table): void {
            $table->dropIndex('commission_rules_default_lookup_index');
            $table->dropColumn('is_default');
        });
    }
};
