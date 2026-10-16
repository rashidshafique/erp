
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_inspection_results', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('quality_inspection_id')
                ->constrained('quality_inspections')
                ->restrictOnDelete();

            $table->foreignUuid('quality_defect_id')
                ->nullable()
                ->constrained('quality_defects')
                ->restrictOnDelete();

            $table->unsignedInteger('sequence')->default(1);

            $table->string('test_code', 50);
            $table->string('test_name', 150);
            $table->string('test_method', 100)->nullable();

            $table->string('result_type', 30)->default('NUMERIC');

            $table->decimal('measured_value', 14, 5)->nullable();
            $table->decimal('minimum_value', 14, 5)->nullable();
            $table->decimal('maximum_value', 14, 5)->nullable();
            $table->string('unit', 30)->nullable();

            $table->text('text_result')->nullable();
            $table->boolean('passed')->nullable();

            $table->string('sample_reference', 100)->nullable();
            $table->string('equipment_reference', 150)->nullable();
            $table->string('tested_by', 150)->nullable();
            $table->timestampTz('tested_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['quality_inspection_id', 'sequence'],
                'quality_inspection_results_inspection_sequence_unique'
            );

            $table->index(['test_code', 'passed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_inspection_results');
    }
};