<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thermal_analysis_tests', function (Blueprint $table) {
            $table->decimal('peak_temp_c', 6, 1)->nullable();          // highest temperature of the sample (pouring temperature of the cup)
            $table->decimal('carbon_reported_pct', 4, 2)->nullable();  // carbon as shown by the analyser, not derived here
            $table->uuid('sample_group_id')->nullable()->index();      // ties the white and grey cup of one sample together
        });
    }

    public function down(): void
    {
        Schema::table('thermal_analysis_tests', function (Blueprint $table) {
            $table->dropIndex(['sample_group_id']);
            $table->dropColumn(['peak_temp_c', 'carbon_reported_pct', 'sample_group_id']);
        });
    }
};
