<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charges and deductions on a sales invoice or credit note (design 19o): each names the ledger account it posts to and an amount,
 * added or taken off after tax. The total becomes the net the customer owes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->jsonb('adjustments')->nullable()->after('tax_withheld');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('adjustments');
        });
    }
};
