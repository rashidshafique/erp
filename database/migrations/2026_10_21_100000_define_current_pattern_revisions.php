<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A pattern revision is a version of the cast-relevant tooling geometry; repairs and repainting belong in the maintenance log.
 * Each pattern has exactly one current revision; older ones are superseded, never deleted. Revisions get a code made from
 * the pattern code (P-0123-R1) and may name the customer drawing revision they follow.
 *
 * Back-fill: a pattern with no revision gets "Rev 0, as-imported baseline". A pattern that has revisions keeps them: the
 * latest approved one (else the latest not obsolete, else the latest) becomes current and the older ones are superseded.
 * Safe to run again: nothing is changed for a pattern that already has a current revision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pattern_revisions', function (Blueprint $table) {
            if (! Schema::hasColumn('pattern_revisions', 'code')) {
                $table->string('code', 60)->nullable()->unique();
            }
            if (! Schema::hasColumn('pattern_revisions', 'sequence')) {
                $table->unsignedInteger('sequence')->nullable();
            }
            if (! Schema::hasColumn('pattern_revisions', 'is_current')) {
                $table->boolean('is_current')->default(false);
            }
            if (! Schema::hasColumn('pattern_revisions', 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable();
            }
            if (! Schema::hasColumn('pattern_revisions', 'legacy_baseline')) {
                $table->boolean('legacy_baseline')->default(false);
            }
            if (! Schema::hasColumn('pattern_revisions', 'drawing_revision')) {
                $table->string('drawing_revision', 40)->nullable();
            }
            if (! Schema::hasColumn('pattern_revisions', 'shrinkage_note')) {
                $table->text('shrinkage_note')->nullable();
            }
        });
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS pattern_revisions_one_current ON pattern_revisions (pattern_id) WHERE is_current');

        $now = now();
        foreach (DB::table('patterns')->orderBy('code')->get(['id', 'code']) as $pattern) {
            $revisions = DB::table('pattern_revisions')->where('pattern_id', $pattern->id)->orderBy('created_at')->orderBy('id')->get();

            if ($revisions->isEmpty()) {
                DB::table('pattern_revisions')->insert([
                    'id' => (string) Str::uuid(), 'pattern_id' => $pattern->id, 'revision' => '0', 'sequence' => 0,
                    'code' => $this->freeCode($pattern->code, 0), 'status' => 'APPROVED', 'is_current' => true, 'legacy_baseline' => true,
                    'change_description' => 'As-imported baseline', 'created_at' => $now, 'updated_at' => $now,
                ]);

                continue;
            }

            $next = (int) $revisions->max('sequence') + ($revisions->whereNotNull('sequence')->isEmpty() ? 0 : 1);
            foreach ($revisions->whereNull('sequence') as $r) {
                DB::table('pattern_revisions')->where('id', $r->id)->update(['sequence' => $next, 'code' => $r->code ?? $this->freeCode($pattern->code, $next)]);
                $r->sequence = $next++;
            }

            if ($revisions->contains('is_current', true)) {
                continue;
            }
            $current = $revisions->where('status', 'APPROVED')->last()
                ?? $revisions->where('status', '!=', 'OBSOLETE')->last()
                ?? $revisions->last();
            DB::table('pattern_revisions')->where('id', $current->id)->update(['is_current' => true, 'superseded_at' => null]);
            DB::table('pattern_revisions')->where('pattern_id', $pattern->id)->where('id', '!=', $current->id)
                ->where('created_at', '<=', $current->created_at)->whereNull('superseded_at')
                ->update(['superseded_at' => $now]);
        }
    }

    /** P-0123-R1 from the pattern code; a pattern without a code uses its revision number only. */
    private function freeCode(?string $patternCode, int $sequence): string
    {
        $base = ($patternCode ?: 'PATTERN').'-R'.$sequence;
        $code = $base;
        for ($i = 2; DB::table('pattern_revisions')->where('code', $code)->exists(); $i++) {
            $code = $base.'-'.$i;
        }

        return $code;
    }

    public function down(): void
    {
        DB::table('pattern_revisions')->where('legacy_baseline', true)
            ->whereNotExists(fn ($q) => $q->from('pattern_revision_cavities')->whereColumn('pattern_revision_cavities.pattern_revision_id', 'pattern_revisions.id'))
            ->delete();
        DB::statement('DROP INDEX IF EXISTS pattern_revisions_one_current');
        Schema::table('pattern_revisions', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'sequence', 'is_current', 'superseded_at', 'legacy_baseline', 'drawing_revision', 'shrinkage_note']);
        });
    }
};
