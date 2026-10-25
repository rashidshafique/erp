<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_revision_id')->constrained('product_revisions')->cascadeOnDelete();
            $table->foreignUuid('material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->string('basis', 20)->default('PER_CASTING');
            $table->string('notes', 300)->nullable();
            $table->timestamps();

            $table->unique(['product_revision_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_lines');
    }
};
