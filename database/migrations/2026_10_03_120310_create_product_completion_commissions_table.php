<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_completion_commissions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('task_id')->unique()->constrained('product_completion_tasks')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            $table->char('currency', 3)->default('IRR');
            $table->decimal('base_amount', 18, 2)->default(0);
            $table->decimal('revision_amount', 18, 2)->default(0);
            $table->decimal('bonus_amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);
            $table->decimal('adjustment_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->json('breakdown_json');
            $table->string('status', 30)->default('calculated');
            $table->dateTime('calculated_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('payable_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('transaction_id')->nullable()->unique();
            $table->char('payment_idempotency_key', 64)->nullable()->unique();
            $table->text('dispute_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'pcc_user_status_index');
            $table->index(['status', 'payable_at'], 'pcc_status_payable_index');
            $table->index(['paid_at', 'status'], 'pcc_paid_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_completion_commissions');
    }
};
