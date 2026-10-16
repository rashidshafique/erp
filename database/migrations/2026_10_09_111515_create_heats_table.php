
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heats', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('heat_number', 80)->unique();

            $table->foreignUuid('furnace_id')
                ->constrained('furnaces')
                ->restrictOnDelete();

            $table->foreignUuid('charge_batch_id')
                ->nullable()
                ->constrained('charge_batches')
                ->restrictOnDelete();

            $table->string('process_type', 30);
            $table->string('status', 30)->default('PLANNED');

            $table->timestampTz('planned_start_at')->nullable();
            $table->timestampTz('actual_start_at')->nullable();
            $table->timestampTz('melt_completed_at')->nullable();
            $table->timestampTz('tapped_at')->nullable();
            $table->timestampTz('closed_at')->nullable();

            $table->decimal('target_metal_weight_kg', 14, 3)->nullable();
            $table->decimal('actual_tapped_weight_kg', 14, 3)->nullable();

            $table->decimal('target_temperature_c', 8, 2)->nullable();
            $table->decimal('actual_tap_temperature_c', 8, 2)->nullable();

            $table->string('operator_name', 100)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['process_type', 'status']);
            $table->index('actual_start_at');
            $table->index('furnace_id', 'heats_furnace_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heats');
    }
};