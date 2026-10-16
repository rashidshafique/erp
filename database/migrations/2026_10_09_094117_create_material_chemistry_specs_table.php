<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('material_chemistry_specs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('material_id')
                ->constrained('materials')
                ->restrictOnDelete();

            $table->string('spec_code', 50);
            $table->string('revision', 20)->default('A');
            $table->string('grade', 80)->nullable();
            $table->string('status', 20)->default('DRAFT');

            $table->decimal('carbon_min', 7, 4)->nullable();
            $table->decimal('carbon_max', 7, 4)->nullable();
            $table->decimal('silicon_min', 7, 4)->nullable();
            $table->decimal('silicon_max', 7, 4)->nullable();
            $table->decimal('manganese_min', 7, 4)->nullable();
            $table->decimal('manganese_max', 7, 4)->nullable();
            $table->decimal('sulphur_min', 7, 4)->nullable();
            $table->decimal('sulphur_max', 7, 4)->nullable();
            $table->decimal('phosphorus_min', 7, 4)->nullable();
            $table->decimal('phosphorus_max', 7, 4)->nullable();

            $table->text('other_elements')->nullable();
            $table->text('notes')->nullable();

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->timestamps();

            $table->unique(
                ['material_id', 'spec_code', 'revision'],
                'material_chem_specs_material_code_rev_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_chemistry_specs');
    }
};
