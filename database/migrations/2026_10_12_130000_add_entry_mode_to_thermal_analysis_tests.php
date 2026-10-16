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
            // CURVE: analysed from a stored cooling curve. MANUAL: values read from the thermal analyser's display.
            $table->string('entry_mode', 10)->default('CURVE');
        });
        DB::statement("ALTER TABLE thermal_analysis_tests ADD CONSTRAINT thermal_analysis_tests_entry_mode_check CHECK (entry_mode IN ('CURVE','MANUAL'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE thermal_analysis_tests DROP CONSTRAINT IF EXISTS thermal_analysis_tests_entry_mode_check');
        Schema::table('thermal_analysis_tests', function (Blueprint $table) {
            $table->dropColumn('entry_mode');
        });
    }
};
