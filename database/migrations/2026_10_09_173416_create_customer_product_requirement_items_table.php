
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_product_requirement_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('customer_product_requirement_id')
                ->constrained('customer_product_requirements')
                ->restrictOnDelete();

            $table->unsignedInteger('sequence')->default(1);

            $table->string('characteristic_code', 100);
            $table->string('characteristic_name', 200);
            $table->string('category', 50)->default('GENERAL');
            $table->string('requirement_type', 40)->default('ACCEPTANCE_LIMIT');
            $table->string('data_type', 30)->default('NUMERIC');

            $table->decimal('minimum_value', 18, 6)->nullable();
            $table->decimal('maximum_value', 18, 6)->nullable();
            $table->decimal('target_value', 18, 6)->nullable();
            $table->string('unit', 40)->nullable();

            $table->string('expected_text', 500)->nullable();
            $table->text('acceptance_criteria')->nullable();

            $table->string('test_method_code', 100)->nullable();
            $table->string('test_method_reference', 200)->nullable();

            $table->string('sampling_method', 50)->nullable();
            $table->decimal('sampling_frequency', 12, 4)->nullable();
            $table->string('sampling_frequency_unit', 30)->nullable();
            $table->unsignedInteger('sample_size')->nullable();

            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_critical')->default(false);
            $table->boolean('requires_recorded_result')->default(true);

            $table->text('reaction_plan')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['customer_product_requirement_id', 'sequence'],
                'customer_product_req_items_sequence_unique'
            );

            $table->index(['characteristic_code', 'category']);
            $table->index(['is_mandatory', 'is_critical']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_product_requirement_items');
    }
};