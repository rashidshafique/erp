
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_measurements', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('process_control_plan_item_id')
                ->nullable()
                ->constrained('process_control_plan_items')
                ->restrictOnDelete();

            $table->foreignUuid('production_order_id')
                ->nullable()
                ->constrained('production_orders')
                ->restrictOnDelete();

            $table->foreignUuid('production_order_operation_id')
                ->nullable()
                ->constrained('production_order_operations')
                ->restrictOnDelete();

            $table->foreignUuid('casting_lot_id')
                ->nullable()
                ->constrained('casting_lots')
                ->restrictOnDelete();

            $table->foreignUuid('heat_id')
                ->nullable()
                ->constrained('heats')
                ->restrictOnDelete();

            $table->foreignUuid('equipment_id')
                ->nullable()
                ->constrained('equipment')
                ->restrictOnDelete();

            $table->unsignedInteger('reading_number')->default(1);

            $table->string('measurement_code', 80);
            $table->string('measurement_name', 150);

            $table->string('result_type', 20)->default('NUMERIC');
            $table->decimal('measured_value', 18, 6)->nullable();
            $table->text('text_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->string('unit', 30)->nullable();

            // Snapshots of the limits applicable when measured.
            $table->decimal('target_value', 18, 6)->nullable();
            $table->decimal('minimum_value', 18, 6)->nullable();
            $table->decimal('maximum_value', 18, 6)->nullable();

            $table->string('result_status', 30)->default('NOT_EVALUATED');

            $table->timestampTz('measured_at');
            $table->string('measurement_source', 30)->default('MANUAL');
            $table->string('sample_reference', 100)->nullable();
            $table->string('instrument_reference', 100)->nullable();
            $table->string('operator_name', 150)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['production_order_id', 'measured_at']);
            $table->index(['casting_lot_id', 'measured_at']);
            $table->index(['heat_id', 'measured_at']);
            $table->index(['equipment_id', 'measured_at']);
            $table->index(['process_control_plan_item_id', 'measured_at']);
            $table->index(['result_status', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_measurements');
    }
};