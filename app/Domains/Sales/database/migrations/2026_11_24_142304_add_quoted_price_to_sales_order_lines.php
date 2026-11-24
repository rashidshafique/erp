<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The price agreed on the quotation a line came from: the price check compares against it instead of the price list. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('quoted_price', 14, 4)->nullable()->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', fn (Blueprint $table) => $table->dropColumn('quoted_price'));
    }
};
