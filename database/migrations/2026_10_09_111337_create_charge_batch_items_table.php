
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_batch_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('charge_batch_id')
                ->constrained('charge_batches')
                ->cascadeOnDelete();

            $table->foreignUuid('material_id')
                ->constrained('materials')
                ->restrictOnDelete();

            $table->unsignedInteger('sequence');

            $table->string('component_role', 40);
            $table->decimal('target_qty_kg', 14, 3)->nullable();
            $table->decimal('target_percent', 8, 4)->nullable();

            $table->decimal('minimum_qty_kg', 14, 3)->nullable();
            $table->decimal('maximum_qty_kg', 14, 3)->nullable();

            $table->boolean('is_required')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['charge_batch_id', 'sequence'],
                'charge_batch_items_batch_sequence_unique'
            );

            $table->index(['material_id', 'charge_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_batch_items');
    }
};