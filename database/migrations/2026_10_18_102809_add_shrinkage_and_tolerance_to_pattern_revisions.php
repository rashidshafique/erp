<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pattern_revisions', function (Blueprint $table) {
            $table->decimal('shrinkage_percent', 5, 3)->nullable();
            $table->unsignedSmallInteger('tolerance_grade')->nullable();
            $table->string('machining_allowance_grade', 1)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pattern_revisions', function (Blueprint $table) {
            $table->dropColumn(['shrinkage_percent', 'tolerance_grade', 'machining_allowance_grade']);
        });
    }
};
