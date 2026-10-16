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
    Schema::create('inventory_reservations', function (Blueprint $table) {
        $table->uuid('id')->primary();

        $table->foreignUuid('material_id')
            ->constrained('materials')
            ->restrictOnDelete();

        $table->foreignUuid('material_lot_id')
            ->nullable()
            ->constrained('material_lots')
            ->restrictOnDelete();

        $table->foreignUuid('stock_location_id')
            ->nullable()
            ->constrained('stock_locations')
            ->restrictOnDelete();

        $table->string('reference_type', 50);
        $table->uuid('reference_id');

        $table->decimal('reserved_qty', 14, 3);
        $table->decimal('fulfilled_qty', 14, 3)->default(0);

        $table->string('status', 20)->default('ACTIVE');
        $table->timestampTz('reserved_at');
        $table->timestampTz('released_at')->nullable();
        $table->string('reserved_by', 100)->nullable();
        $table->text('notes')->nullable();

        $table->timestamps();

        $table->index(['material_id', 'status']);
        $table->index(['reference_type', 'reference_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('inventory_reservations');
}
};
