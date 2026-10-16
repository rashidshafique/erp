<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturing_method_sand_options', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('manufacturing_method_id')
                ->constrained('manufacturing_methods')
                ->cascadeOnDelete();

            $table->foreignUuid('sand_system_id')
                ->constrained('sand_systems')
                ->restrictOnDelete();

            $table->foreignUuid('sand_recipe_id')
                ->nullable()
                ->constrained('sand_recipes')
                ->restrictOnDelete();

            $table->unsignedInteger('preference_rank')->default(1);

            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_allowed')->default(true);

            $table->decimal('min_batch_weight_kg', 12, 3)->nullable();
            $table->decimal('max_batch_weight_kg', 12, 3)->nullable();

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'manufacturing_method_id',
                'sand_system_id',
                'sand_recipe_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing_method_sand_options');
    }
};