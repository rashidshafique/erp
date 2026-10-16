<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pattern_revision_cavities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pattern_revision_id')->constrained('pattern_revisions')->restrictOnDelete();
            $table->unsignedSmallInteger('cavity_number');
            $table->foreignUuid('product_revision_id')->constrained('product_revisions')->restrictOnDelete();
            // WHOLE = the cavity makes a complete casting of the product.
            // PART  = the cavity makes one component/section of the product.
            $table->string('cavity_role', 10)->default('WHOLE');
            $table->string('component_label', 100)->nullable();
            $table->unsignedSmallInteger('pieces_per_product')->default(1);
            $table->decimal('casting_weight_kg', 12, 4)->nullable();
            $table->decimal('poured_weight_kg', 12, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['pattern_revision_id', 'cavity_number'], 'prc_revision_cavity_unique');
            $table->index('product_revision_id');
        });

        DB::statement("ALTER TABLE pattern_revision_cavities ADD CONSTRAINT prc_cavity_number_positive CHECK (cavity_number >= 1)");
        DB::statement("ALTER TABLE pattern_revision_cavities ADD CONSTRAINT prc_role_valid CHECK (cavity_role IN ('WHOLE','PART'))");
        DB::statement("ALTER TABLE pattern_revision_cavities ADD CONSTRAINT prc_part_needs_label CHECK (cavity_role = 'WHOLE' OR (component_label IS NOT NULL AND length(trim(component_label)) > 0))");
        DB::statement("ALTER TABLE pattern_revision_cavities ADD CONSTRAINT prc_pieces_positive CHECK (pieces_per_product >= 1)");
        DB::statement("ALTER TABLE pattern_revision_cavities ADD CONSTRAINT prc_weights_valid CHECK ((casting_weight_kg IS NULL OR casting_weight_kg > 0) AND (poured_weight_kg IS NULL OR poured_weight_kg > 0) AND (casting_weight_kg IS NULL OR poured_weight_kg IS NULL OR poured_weight_kg >= casting_weight_kg))");

        // Back-fill from cavities that were already defined on moulds, but only
        // where a pattern revision + cavity number maps to exactly one product revision.
        $rows = DB::table('mould_cavities')
            ->select('pattern_revision_id', 'cavity_number', DB::raw('min(product_revision_id::text) as product_revision_id'), DB::raw('count(distinct product_revision_id) as n'), DB::raw('max(nominal_casting_weight_kg) as w'))
            ->groupBy('pattern_revision_id', 'cavity_number')
            ->get();

        foreach ($rows as $r) {
            if ((int) $r->n !== 1) {
                continue;
            }
            DB::table('pattern_revision_cavities')->insert([
                'id' => (string) Str::uuid(),
                'pattern_revision_id' => $r->pattern_revision_id,
                'cavity_number' => $r->cavity_number,
                'product_revision_id' => $r->product_revision_id,
                'cavity_role' => 'WHOLE',
                'pieces_per_product' => 1,
                'casting_weight_kg' => $r->w,
                'notes' => 'Back-filled from existing mould cavities.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_revision_cavities');
    }
};
