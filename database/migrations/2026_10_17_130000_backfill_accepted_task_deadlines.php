<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_completion_tasks')
            ->whereNotNull('accepted_at')
            ->whereNotNull('due_at')
            ->whereNull('completion_duration_seconds')
            ->whereIn('status', ['accepted', 'in_progress', 'submitted', 'changes_requested', 'resubmitted'])
            ->select(['id', 'created_at', 'accepted_at', 'due_at'])
            ->chunkById(100, function ($tasks): void {
                foreach ($tasks as $task) {
                    $duration = max(1, (int) Carbon::parse($task->created_at)
                        ->diffInSeconds(Carbon::parse($task->due_at), false));
                    $newDueAt = Carbon::parse($task->accepted_at)->addSeconds($duration);

                    DB::table('product_completion_tasks')->where('id', $task->id)
                        ->whereNotNull('accepted_at')->whereNull('completion_duration_seconds')
                        ->update([
                            'completion_duration_seconds' => $duration,
                            'due_at' => $newDueAt,
                            'lock_version' => DB::raw('lock_version + 1'),
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('product_completion_tasks')
            ->whereNotNull('accepted_at')
            ->whereNotNull('completion_duration_seconds')
            ->whereIn('status', ['accepted', 'in_progress', 'submitted', 'changes_requested', 'resubmitted'])
            ->select(['id', 'created_at', 'completion_duration_seconds'])
            ->chunkById(100, function ($tasks): void {
                foreach ($tasks as $task) {
                    DB::table('product_completion_tasks')->where('id', $task->id)
                        ->update(['due_at' => Carbon::parse($task->created_at)->addSeconds((int) $task->completion_duration_seconds),
                            'completion_duration_seconds' => null,
                            'lock_version' => DB::raw('lock_version + 1')]);
                }
            });
    }
};
