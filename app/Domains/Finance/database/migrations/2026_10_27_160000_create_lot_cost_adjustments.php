<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each time a supplier bill moves the cost of lots received at the order price: from and to which unit cost, how much of the lot
 * was still in stock (now at the new cost) and how much was already used, and the difference on what was used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_cost_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('material_lot_id')->constrained('material_lots')->cascadeOnDelete();
            $table->foreignUuid('supplier_bill_id')->nullable()->constrained('supplier_bills')->nullOnDelete();
            $table->string('reason', 20);
            $table->decimal('from_unit_cost', 14, 4);
            $table->decimal('to_unit_cost', 14, 4);
            $table->decimal('quantity_on_hand', 14, 3);
            $table->decimal('quantity_used', 14, 3);
            $table->decimal('variance_on_used', 14, 2);
            $table->timestamps();

            $table->index('supplier_bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_cost_adjustments');
    }
};
