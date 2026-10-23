<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional chemistry of a material, one row per element. Used to predict what a charge will melt to: `nominal_pct` is the
     * typical analysis, `min_pct` and `max_pct` the spread, and `recovery_pct` how much of the element ends up in the iron
     * (below 100 for what burns off, above 100 for what the material picks up).
     */
    public function up(): void
    {
        Schema::create('material_elements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('material_id')->constrained('materials')->cascadeOnDelete();
            $table->string('element', 8);
            $table->decimal('nominal_pct', 8, 4);
            $table->decimal('min_pct', 8, 4)->nullable();
            $table->decimal('max_pct', 8, 4)->nullable();
            $table->decimal('recovery_pct', 6, 2)->default(100);
            $table->timestamps();
            $table->unique(['material_id', 'element']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_elements');
    }
};
