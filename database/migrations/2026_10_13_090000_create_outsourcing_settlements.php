<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outsourcing_settlements', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('include_previous')->default(true);
            foreach (['gross_amount', 'previous_amount', 'period_amount', 'adjustment_amount', 'net_amount'] as $field) {
                $table->decimal($field, 18, 2)->default(0);
            }
            $table->text('adjustment_reason')->nullable();
            $table->string('status', 20)->default('draft');
            $table->char('creation_key', 64)->unique();
            $table->char('request_hash', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::table('product_completion_commissions', function (Blueprint $table): void {
            $table->foreignId('settlement_id')->nullable()->constrained('outsourcing_settlements')->restrictOnDelete();
            $table->index(['user_id', 'currency', 'status', 'calculated_at'], 'pcc_settlement_eligibility');
        });
        Schema::create('outsourcing_settlement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')->constrained('outsourcing_settlements')->restrictOnDelete();
            $table->foreignId('commission_id')->constrained('product_completion_commissions')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->boolean('is_previous');
            $table->json('snapshot_json');
            $table->timestamps();
            $table->unique(['settlement_id', 'commission_id'], 'osi_statement_commission');
        });
        Schema::create('outsourcing_settlement_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')->unique()->constrained('outsourcing_settlements')->restrictOnDelete();
            $table->string('reference', 191)->unique();
            $table->char('idempotency_key', 64)->unique();
            $table->decimal('amount', 18, 2);
            $table->foreignId('paid_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outsourcing_settlement_payments');
        Schema::dropIfExists('outsourcing_settlement_items');
        Schema::table('product_completion_commissions', function (Blueprint $table): void {
            $table->dropForeign(['settlement_id']);
            $table->dropColumn('settlement_id');
            $table->dropIndex('pcc_settlement_eligibility');
        });
        Schema::dropIfExists('outsourcing_settlements');
    }
};
