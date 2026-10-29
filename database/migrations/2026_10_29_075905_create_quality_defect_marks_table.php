<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Defects found at inspection, as the floor marks them: how many pieces of a lot had the defect, rejected or sent to rework,
 * usually by the pattern cavity they came from ("cavity 5: 20 cold shuts"), with where on the casting and, when asked, the pieces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_defect_marks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quality_inspection_id')->constrained('quality_inspections')->cascadeOnDelete();
            $table->foreignUuid('casting_lot_id')->constrained('casting_lots');
            $table->foreignUuid('product_revision_id')->constrained('product_revisions');
            $table->foreignUuid('quality_defect_id')->constrained('quality_defects');
            $table->foreignUuid('pattern_revision_id')->nullable()->constrained('pattern_revisions');
            $table->unsignedSmallInteger('cavity_number')->nullable();
            $table->string('location', 150)->nullable();
            $table->unsignedInteger('quantity');
            $table->string('disposition', 8);
            $table->text('piece_ids')->nullable();
            $table->timestamps();
            $table->index(['pattern_revision_id', 'cavity_number']);
        });
        DB::statement("ALTER TABLE quality_defect_marks ADD CONSTRAINT quality_defect_marks_disposition_valid CHECK (disposition IN ('REJECT','REWORK'))");
        DB::statement('ALTER TABLE quality_defect_marks ADD CONSTRAINT quality_defect_marks_quantity_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_defect_marks');
    }
};
