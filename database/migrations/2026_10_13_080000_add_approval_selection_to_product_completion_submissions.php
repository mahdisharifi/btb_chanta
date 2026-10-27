<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_completion_submissions', function (Blueprint $table): void {
            $table->json('approval_selection_json')->nullable()->after('review_note');
        });
    }

    public function down(): void
    {
        Schema::table('product_completion_submissions', function (Blueprint $table): void {
            $table->dropColumn('approval_selection_json');
        });
    }
};
