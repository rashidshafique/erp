<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Whether a customer takes part of a lot, and the fewest pieces worth sending; an order line may override both. Off by default: an open NCR holds the whole lot. */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('allows_partial_shipments')->default(false);
        });
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->boolean('partial_shipments')->nullable();
            $table->unsignedInteger('min_ship_qty')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropColumn(['partial_shipments', 'min_ship_qty']);
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('allows_partial_shipments');
        });
    }
};
