<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moulding_stations', function (Blueprint $t) {
            // How many patterns this station moulds at the same time. An automated line is 1; a manual bench can hold several plates or loose patterns.
            $t->unsignedSmallInteger('max_patterns')->default(1);
            $t->uuid('default_moulding_team_id')->nullable();
            $t->foreign('default_moulding_team_id')->references('id')->on('moulding_teams')->nullOnDelete();
        });
        DB::statement('ALTER TABLE moulding_stations ADD CONSTRAINT moulding_stations_max_patterns_chk CHECK (max_patterns BETWEEN 1 AND 20)');
        // Manual stations that already exist keep working as before: allow four at once, which the user can change.
        DB::table('moulding_stations')->whereIn('station_type', ['FLOOR', 'JOLT_SQUEEZE', 'OTHER'])->update(['max_patterns' => 4]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE moulding_stations DROP CONSTRAINT IF EXISTS moulding_stations_max_patterns_chk');
        Schema::table('moulding_stations', function (Blueprint $t) {
            $t->dropForeign(['default_moulding_team_id']);
            $t->dropColumn(['max_patterns', 'default_moulding_team_id']);
        });
    }
};
