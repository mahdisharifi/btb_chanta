<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->unsignedInteger('completion_duration_seconds')->nullable();
        });

        DB::table('product_completion_tasks')->whereNull('accepted_at')
            ->whereIn('status', ['draft', 'available', 'assigned'])->whereNotNull('due_at')
            ->select(['id', 'created_at', 'due_at'])->chunkById(100, function ($tasks): void {
                foreach ($tasks as $task) {
                    $seconds = max(1, (int) Carbon::parse($task->created_at)->diffInSeconds(Carbon::parse($task->due_at), false));
                    DB::table('product_completion_tasks')->where('id', $task->id)->whereNull('accepted_at')
                        ->whereIn('status', ['draft', 'available', 'assigned'])
                        ->update(['completion_duration_seconds' => $seconds, 'due_at' => null,
                            'lock_version' => DB::raw('lock_version + 1')]);
                }
            });
    }

    public function down(): void
    {
        DB::table('product_completion_tasks')->whereNull('due_at')->whereNotNull('completion_duration_seconds')
            ->select(['id', 'completion_duration_seconds'])->chunkById(100, function ($tasks): void {
                foreach ($tasks as $task) {
                    DB::table('product_completion_tasks')->where('id', $task->id)->whereNull('due_at')
                        ->update(['due_at' => now()->addSeconds($task->completion_duration_seconds)]);
                }
            });
        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->dropColumn('completion_duration_seconds');
        });
    }
};
