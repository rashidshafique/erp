
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casting_lot_stage_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('casting_lot_id')
                ->constrained('casting_lots')
                ->restrictOnDelete();

            $table->foreignUuid('stage_definition_id')
                ->constrained('production_stage_definitions')
                ->restrictOnDelete();

            $table->foreignUuid('equipment_id')
                ->nullable()
                ->constrained('equipment')
                ->restrictOnDelete();

            $table->string('event_type', 30)->default('COMPLETED');
            $table->string('status', 30)->default('COMPLETED');

            $table->unsignedInteger('quantity_in')->default(0);
            $table->unsignedInteger('quantity_accepted')->default(0);
            $table->unsignedInteger('quantity_rejected')->default(0);
            $table->unsignedInteger('quantity_out')->default(0);

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->string('operator_name', 100)->nullable();
            $table->string('shift_code', 30)->nullable();

            $table->string('rejection_reason', 100)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(
                ['casting_lot_id', 'created_at'],
                'casting_lot_stage_events_lot_created_index'
            );

            $table->index(
                ['stage_definition_id', 'status'],
                'casting_lot_stage_events_stage_status_index'
            );

            $table->index(['equipment_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casting_lot_stage_events');
    }
};