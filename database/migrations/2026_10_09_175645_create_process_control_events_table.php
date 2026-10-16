
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_control_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_number', 50)->unique();

            $table->foreignUuid('process_measurement_id')
                ->nullable()
                ->constrained('process_measurements')
                ->restrictOnDelete();

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

            $table->string('event_type', 40)->default('OUT_OF_CONTROL');
            $table->string('severity', 20)->default('MAJOR');
            $table->string('status', 30)->default('OPEN');

            $table->timestampTz('detected_at');
            $table->string('detected_by', 150)->nullable();

            $table->text('description');
            $table->text('immediate_action')->nullable();
            $table->text('containment_action')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->text('preventive_action')->nullable();

            $table->string('disposition', 40)->nullable();
            $table->string('assigned_to', 150)->nullable();
            $table->timestampTz('target_completion_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->string('closed_by', 150)->nullable();
            $table->text('verification_notes')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index(['detected_at', 'status']);
            $table->index(['production_order_id', 'detected_at']);
            $table->index(['casting_lot_id', 'detected_at']);
            $table->index(['heat_id', 'detected_at']);
            $table->index(['equipment_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_control_events');
    }
};