<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sand_recipe_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('sand_recipe_id')
                ->constrained('sand_recipes')
                ->cascadeOnDelete();

            $table->string('material_code', 40);
            $table->string('material_name');

            $table->unsignedInteger('sequence');

            $table->string('basis', 20)->default('PERCENT');

            $table->decimal('target_percent', 10, 4)->nullable();

            $table->decimal('target_qty_kg', 12, 4)->nullable();

            $table->decimal('min_percent', 10, 4)->nullable();
            $table->decimal('max_percent', 10, 4)->nullable();

            $table->boolean('is_required')->default(true);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'sand_recipe_id',
                'sequence'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sand_recipe_items');
    }
};