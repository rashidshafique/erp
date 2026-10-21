<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thermal_analysis_tests', function (Blueprint $table) {
            $table->string('ce_source', 12)->nullable();
        });
        DB::table('thermal_analysis_tests')->whereNotNull('ce_from_tl')->update(['ce_source' => 'CALCULATED']);
    }

    public function down(): void
    {
        Schema::table('thermal_analysis_tests', fn (Blueprint $table) => $table->dropColumn('ce_source'));
    }
};
