
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heat_material_consumptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('heat_id')
                ->constrained('heats')
                ->cascadeOnDelete();

            // Required for purchased or stock-tracked materials.
            // Nullable for internally generated returns recorded separately.
            $table->foreignUuid('material_id')
                ->nullable()
                ->constrained('materials')
                ->restrictOnDelete();

            $table->foreignUuid('material_lot_id')
                ->nullable()
                ->constrained('material_lots')
                ->restrictOnDelete();

            $table->unsignedInteger('sequence');

            $table->string('consumption_type', 40);
            $table->string('source_reference', 100)->nullable();

            $table->decimal('planned_qty_kg', 14, 3)->nullable();
            $table->decimal('actual_qty_kg', 14, 3);

            // Cost assigned to this heat per kilogram.
            $table->decimal('transfer_cost_per_kg', 14, 4)->nullable();
            $table->decimal('actual_total_cost', 16, 4)->nullable();

            $table->timestampTz('consumed_at')->nullable();
            $table->string('recorded_by', 100)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['heat_id', 'sequence'],
                'heat_material_consumptions_heat_sequence_unique'
            );

            $table->index(['material_id', 'consumed_at']);
            $table->index(['material_lot_id', 'consumed_at']);
            $table->index(['heat_id', 'consumption_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heat_material_consumptions');
    }
};