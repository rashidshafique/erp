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
    Schema::create('stock_movements', function (Blueprint $table) {
        $table->uuid('id')->primary();

        $table->string('movement_number', 80)->unique();
        $table->string('movement_type', 40);

        $table->foreignUuid('material_id')
            ->constrained('materials')
            ->restrictOnDelete();

        $table->foreignUuid('material_lot_id')
            ->constrained('material_lots')
            ->restrictOnDelete();

        $table->foreignUuid('from_location_id')
            ->nullable()
            ->constrained('stock_locations')
            ->restrictOnDelete();

        $table->foreignUuid('to_location_id')
            ->nullable()
            ->constrained('stock_locations')
            ->restrictOnDelete();

        $table->decimal('quantity', 14, 3);
        $table->string('uom', 20)->default('kg');

        $table->decimal('unit_cost', 14, 4)->nullable();
        $table->decimal('total_cost', 16, 4)->nullable();

        $table->string('source_type', 50)->nullable();
        $table->uuid('source_id')->nullable();

        $table->timestampTz('occurred_at');
        $table->string('recorded_by', 100)->nullable();
        $table->text('reason')->nullable();

        $table->timestamps();

        $table->index(['material_id', 'occurred_at']);
        $table->index(['material_lot_id', 'occurred_at']);
        $table->index(['from_location_id', 'occurred_at']);
        $table->index(['to_location_id', 'occurred_at']);
        $table->index(['source_type', 'source_id']);
    });
}



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
