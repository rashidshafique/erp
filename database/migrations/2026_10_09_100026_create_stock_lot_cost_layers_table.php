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
        Schema::create('stock_lot_cost_layers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('material_lot_id')
                ->constrained('material_lots')
                ->restrictOnDelete();

            $table->foreignUuid('stock_movement_id')
                ->nullable()
                ->constrained('stock_movements')
                ->restrictOnDelete();

            $table->decimal('original_qty', 14, 3);
            $table->decimal('remaining_qty', 14, 3);
            $table->decimal('unit_cost', 14, 4);
            $table->string('currency_code', 3)->default('PKR');

            $table->timestampTz('received_at');
            $table->timestamps();

            $table->index(['material_lot_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_lot_cost_layers');
    }
};
