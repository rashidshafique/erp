<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cost and weight figures on a pattern revision (quoting reads them from the current revision only), the link from a copied
 * cavity to the revision it was copied from, and the defect and cavities recorded on a moulding run.
 *
 * Back-fill: a moulding run with no pattern revision takes the one named on its work-list item. A run that already has a
 * revision keeps it: past work stays on the revision it was made under. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pattern_revisions', function (Blueprint $table) {
            foreach ([
                'modification_cost' => fn () => $table->decimal('modification_cost', 14, 2)->nullable(),
                'cost_note' => fn () => $table->text('cost_note')->nullable(),
                'casting_weight_kg' => fn () => $table->decimal('casting_weight_kg', 12, 4)->nullable(),
                'pour_weight_kg' => fn () => $table->decimal('pour_weight_kg', 12, 4)->nullable(),
                'pieces_per_mould' => fn () => $table->unsignedInteger('pieces_per_mould')->nullable(),
            ] as $column => $add) {
                if (! Schema::hasColumn('pattern_revisions', $column)) {
                    $add();
                }
            }
        });
        DB::statement('ALTER TABLE pattern_revisions DROP CONSTRAINT IF EXISTS pattern_revisions_weights_valid');
        DB::statement('ALTER TABLE pattern_revisions ADD CONSTRAINT pattern_revisions_weights_valid CHECK ((casting_weight_kg IS NULL OR casting_weight_kg > 0) AND (pour_weight_kg IS NULL OR pour_weight_kg > 0) AND (casting_weight_kg IS NULL OR pour_weight_kg IS NULL OR pour_weight_kg >= casting_weight_kg) AND (modification_cost IS NULL OR modification_cost >= 0))');

        Schema::table('pattern_revision_cavities', function (Blueprint $table) {
            if (! Schema::hasColumn('pattern_revision_cavities', 'copied_from_revision_id')) {
                $table->foreignUuid('copied_from_revision_id')->nullable()->constrained('pattern_revisions')->restrictOnDelete();
            }
            if (! Schema::hasColumn('pattern_revision_cavities', 'needs_review')) {
                $table->boolean('needs_review')->default(false);
            }
        });

        Schema::table('moulding_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('moulding_runs', 'quality_defect_id')) {
                $table->foreignUuid('quality_defect_id')->nullable()->constrained('quality_defects')->nullOnDelete();
            }
            if (! Schema::hasColumn('moulding_runs', 'cavity_numbers')) {
                $table->json('cavity_numbers')->nullable();
            }
        });

        DB::statement('UPDATE moulding_runs r SET pattern_revision_id = i.pattern_revision_id
            FROM moulding_work_list_items i
            WHERE r.pattern_revision_id IS NULL AND i.id = r.moulding_work_list_item_id AND i.pattern_revision_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('moulding_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quality_defect_id');
            $table->dropColumn('cavity_numbers');
        });
        Schema::table('pattern_revision_cavities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('copied_from_revision_id');
            $table->dropColumn('needs_review');
        });
        DB::statement('ALTER TABLE pattern_revisions DROP CONSTRAINT IF EXISTS pattern_revisions_weights_valid');
        Schema::table('pattern_revisions', function (Blueprint $table) {
            $table->dropColumn(['modification_cost', 'cost_note', 'casting_weight_kg', 'pour_weight_kg', 'pieces_per_mould']);
        });
    }
};
