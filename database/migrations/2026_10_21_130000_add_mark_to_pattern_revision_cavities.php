<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The mark engraved or embossed in a cavity (a number or code that shows on every casting from it, used to trace a defect
 * back to its cavity). Optional, and unique within a pattern revision when given.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pattern_revision_cavities', 'cavity_mark')) {
            Schema::table('pattern_revision_cavities', function (Blueprint $table) {
                $table->string('cavity_mark', 20)->nullable();
            });
        }
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS prc_revision_mark_unique ON pattern_revision_cavities (pattern_revision_id, upper(cavity_mark)) WHERE cavity_mark IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS prc_revision_mark_unique');
        Schema::table('pattern_revision_cavities', function (Blueprint $table) {
            $table->dropColumn('cavity_mark');
        });
    }
};
