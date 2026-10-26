<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A line can repeat weekly or monthly with no new customer PO: each period adds a delivery of repeat_qty and grows ordered_qty by it. */
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->string('repeat_every', 10)->nullable()->after('requested_delivery_date');
            $table->decimal('repeat_qty', 14, 3)->nullable()->after('repeat_every');
            $table->date('repeat_from')->nullable()->after('repeat_qty');
            $table->date('repeat_until')->nullable()->after('repeat_from');
            $table->date('repeated_to')->nullable()->after('repeat_until');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropColumn(['repeat_every', 'repeat_qty', 'repeat_from', 'repeat_until', 'repeated_to']);
        });
    }
};
