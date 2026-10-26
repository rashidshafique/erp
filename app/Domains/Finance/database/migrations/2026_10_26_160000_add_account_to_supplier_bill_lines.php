<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The ledger account a bill line is charged to, when the general ledger is in use. No foreign key: the ledger is its own module. */
    public function up(): void
    {
        Schema::table('supplier_bill_lines', function (Blueprint $table) {
            $table->uuid('account_id')->nullable()->after('purchase_order_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bill_lines', function (Blueprint $table) {
            $table->dropColumn('account_id');
        });
    }
};
