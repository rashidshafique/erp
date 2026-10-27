<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money paid before the bill or invoice exists can name the order it is an advance for, so it goes on that order's bill or
 * invoice first and never on another order's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $t) {
            $t->uuid('purchase_order_id')->nullable()->index();
        });
        Schema::table('receipts', function (Blueprint $t) {
            $t->uuid('sales_order_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', fn (Blueprint $t) => $t->dropColumn('purchase_order_id'));
        Schema::table('receipts', fn (Blueprint $t) => $t->dropColumn('sales_order_id'));
    }
};
