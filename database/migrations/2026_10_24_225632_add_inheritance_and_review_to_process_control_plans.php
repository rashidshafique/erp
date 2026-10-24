<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_control_plans', function (Blueprint $table) {
            $table->boolean('follows_default')->default(false);
            $table->boolean('is_default')->default(false);
            $table->foreignUuid('based_on_plan_id')->nullable()->constrained('process_control_plans')->nullOnDelete();
            $table->timestampTz('needs_review_at')->nullable();
            $table->string('review_reason', 300)->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('reviewed_by', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('process_control_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('based_on_plan_id');
            $table->dropColumn(['follows_default', 'is_default', 'needs_review_at', 'review_reason', 'reviewed_at', 'reviewed_by']);
        });
    }
};
