<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mould arrangement and pattern type were mixed: "matchplate", "loose" and "gated" are tooling, not ways of building a mould.
 * A revision that carried one of those is a horizontally parted two-flask mould, so it becomes cope and drag. Nothing else changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pattern_revisions')->whereIn('mould_arrangement', ['MATCHPLATE', 'LOOSE', 'GATED'])->update(['mould_arrangement' => 'COPE_DRAG']);
    }

    public function down(): void
    {
        // The old values cannot be told apart again; they all read as cope and drag.
    }
};
