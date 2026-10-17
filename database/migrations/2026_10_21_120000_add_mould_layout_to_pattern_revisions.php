<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How a pattern revision is moulded. A mould is closed by a bottom and a top flask (or, in a flaskless vertical line, by the
 * next block); castings form at the joints. A cope-and-drag mould has one joint, so one set of castings. A stack of N
 * sections on one sprue has N − 1 joints, each holding the cavity layout once.
 *
 *   castings per mould = casting levels × cavities on the layout
 *   metal per mould    = castings with their own gating + runners on each level + the shared sprue and pouring basin
 *                        (or the weighed total, when it is recorded)
 *
 * layers_per_mould keeps its meaning: flask sections in one mould (2 for cope and drag). Back-fill: the arrangement follows
 * the pattern type and every revision has one casting level, which is how the app counted castings so far. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pattern_revisions', function (Blueprint $table) {
            foreach ([
                'mould_arrangement' => fn () => $table->string('mould_arrangement', 30)->nullable(),
                'casting_levels' => fn () => $table->unsignedSmallInteger('casting_levels')->default(1),
                'runner_weight_per_level_kg' => fn () => $table->decimal('runner_weight_per_level_kg', 12, 4)->nullable(),
                'sprue_weight_kg' => fn () => $table->decimal('sprue_weight_kg', 12, 4)->nullable(),
                'poured_weight_per_mould_kg' => fn () => $table->decimal('poured_weight_per_mould_kg', 12, 4)->nullable(),
            ] as $column => $add) {
                if (! Schema::hasColumn('pattern_revisions', $column)) {
                    $add();
                }
            }
        });
        DB::statement('ALTER TABLE pattern_revisions DROP CONSTRAINT IF EXISTS pattern_revisions_layout_valid');
        DB::statement('ALTER TABLE pattern_revisions ADD CONSTRAINT pattern_revisions_layout_valid CHECK (casting_levels >= 1
            AND (runner_weight_per_level_kg IS NULL OR runner_weight_per_level_kg >= 0) AND (sprue_weight_kg IS NULL OR sprue_weight_kg >= 0)
            AND (poured_weight_per_mould_kg IS NULL OR poured_weight_per_mould_kg > 0))');

        DB::statement("UPDATE pattern_revisions r SET mould_arrangement = CASE p.pattern_type
                WHEN 'MATCHPLATE' THEN 'MATCHPLATE' WHEN 'COPE_DRAG' THEN 'COPE_DRAG' WHEN 'STACK_PLATE' THEN 'STACK'
                WHEN 'VERTICAL_PLATES' THEN 'VERTICAL' WHEN 'GATED' THEN 'GATED' WHEN 'LOOSE_PATTERN' THEN 'LOOSE' WHEN 'SPLIT_PATTERN' THEN 'LOOSE'
                ELSE 'OTHER' END
            FROM patterns p WHERE p.id = r.pattern_id AND r.mould_arrangement IS NULL");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pattern_revisions DROP CONSTRAINT IF EXISTS pattern_revisions_layout_valid');
        Schema::table('pattern_revisions', function (Blueprint $table) {
            $table->dropColumn(['mould_arrangement', 'casting_levels', 'runner_weight_per_level_kg', 'sprue_weight_kg', 'poured_weight_per_mould_kg']);
        });
    }
};
