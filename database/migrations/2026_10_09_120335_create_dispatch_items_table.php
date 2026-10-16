
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('dispatch_id')
                ->constrained('dispatches')
                ->restrictOnDelete();

            $table->foreignUuid('packing_record_item_id')
                ->constrained('packing_record_items')
                ->restrictOnDelete();

            $table->foreignUuid('sales_order_line_id')
                ->nullable()
                ->constrained('sales_order_lines')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity_dispatched');
            $table->decimal('net_weight_kg', 12, 3)->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['dispatch_id', 'packing_record_item_id']);
            $table->index('sales_order_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_items');
    }
};