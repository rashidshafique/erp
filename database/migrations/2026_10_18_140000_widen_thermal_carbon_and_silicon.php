<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Carbon and silicon can be keyed in to four decimal places, so the columns that keep them must hold four. */
    public function up(): void
    {
        foreach (['carbon_reported_pct', 'silicon_used', 'carbon_from_tl'] as $column) {
            DB::statement("ALTER TABLE thermal_analysis_tests ALTER COLUMN {$column} TYPE numeric(6,4)");
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE thermal_analysis_tests ALTER COLUMN carbon_reported_pct TYPE numeric(4,2)');
        DB::statement('ALTER TABLE thermal_analysis_tests ALTER COLUMN silicon_used TYPE numeric(5,2)');
        DB::statement('ALTER TABLE thermal_analysis_tests ALTER COLUMN carbon_from_tl TYPE numeric(5,2)');
    }
};
