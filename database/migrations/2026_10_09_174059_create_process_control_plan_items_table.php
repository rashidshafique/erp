
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_control_plan_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('process_control_plan_id')
                ->constrained('process_control_plans')
                ->restrictOnDelete();

            $table->foreignUuid('operation_id')
                ->nullable()
                ->constrained('operations')
                ->restrictOnDelete();

            $table->foreignUuid('stage_definition_id')
                ->nullable()
                ->constrained('production_stage_definitions')
                ->restrictOnDelete();

            $table->unsignedInteger('sequence')->default(1);

            $table->string('parameter_code', 100);
            $table->string('parameter_name', 200);
            $table->string('control_category', 50)->default('PROCESS');
            $table->string('control_type', 40)->default('MONITORING');
            $table->string('data_type', 30)->default('NUMERIC');

            $table->decimal('minimum_value', 18, 6)->nullable();
            $table->decimal('maximum_value', 18, 6)->nullable();
            $table->decimal('target_value', 18, 6)->nullable();
            $table->string('unit', 40)->nullable();

            $table->string('expected_text', 500)->nullable();
            $table->text('control_method')->nullable();
            $table->string('measurement_method_code', 100)->nullable();
            $table->string('measurement_method_reference', 200)->nullable();

            $table->string('monitoring_frequency', 50)->nullable();
            $table->decimal('frequency_quantity', 12, 4)->nullable();
            $table->string('frequency_unit', 30)->nullable();
            $table->unsignedInteger('sample_size')->nullable();

            $table->string('responsible_role', 100)->nullable();
            $table->boolean('requires_recorded_result')->default(true);
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_critical')->default(false);

            $table->text('reaction_plan')->nullable();
            $table->text('safety_precautions')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['process_control_plan_id', 'sequence'],
                'process_control_items_sequence_unique'
            );

            $table->index(['parameter_code', 'control_category']);
            $table->index(['operation_id', 'stage_definition_id']);
            $table->index(['is_mandatory', 'is_critical']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_control_plan_items');
    }
};