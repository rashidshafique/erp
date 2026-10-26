<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Each level can say when a mould's flasks are freed; empty follows the level above (mould → run → work list row → pattern → sand system → flask board rule). */
    private const TABLES = ['sand_systems', 'patterns', 'moulding_work_list_items', 'moulding_runs', 'mould_assemblies'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('flasks_freed', 20)->nullable());
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_flasks_freed_chk CHECK (flasks_freed IS NULL OR flasks_freed IN ('POUR','SHAKEOUT'))");
        }
        // Molasses-bonded (Sheera) moulds are stripped once poured; the sand cake cools without its flask.
        DB::table('sand_systems')->where('system_type', 'MOLASSES_BONDED')->update(['flasks_freed' => 'POUR']);
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_flasks_freed_chk");
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('flasks_freed'));
        }
    }
};
