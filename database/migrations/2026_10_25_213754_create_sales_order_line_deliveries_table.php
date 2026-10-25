<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_line_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_order_line_id')->constrained('sales_order_lines')->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('quantity', 14, 3);
            $table->string('notes', 200)->nullable();
            $table->timestamps();

            $table->index(['sales_order_line_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_line_deliveries');
    }
};
