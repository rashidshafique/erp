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
        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('sales_order_id')
                ->constrained('sales_orders')
                ->cascadeOnDelete();

            $table->foreignUuid('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignUuid('product_revision_id')
                ->constrained('product_revisions')
                ->restrictOnDelete();

            $table->unsignedInteger('line_number');

            $table->decimal('ordered_qty', 14, 3);

            $table->decimal('unit_price', 14, 4)->nullable();

            $table->string('uom', 20)->default('piece');

            $table->date('requested_delivery_date')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['sales_order_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
    }
};
