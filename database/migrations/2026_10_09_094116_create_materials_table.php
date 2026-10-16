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
        Schema::create('materials', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('material_type', 40);
            $table->string('base_uom', 20)->default('kg');

            $table->string('status', 20)->default('ACTIVE');
            $table->boolean('is_stock_tracked')->default(true);
            $table->boolean('requires_lot_tracking')->default(true);

            $table->decimal('minimum_stock_qty', 14, 3)->nullable();
            $table->decimal('reorder_qty', 14, 3)->nullable();
            $table->decimal('standard_cost_per_uom', 14, 4)->nullable();

            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['material_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
