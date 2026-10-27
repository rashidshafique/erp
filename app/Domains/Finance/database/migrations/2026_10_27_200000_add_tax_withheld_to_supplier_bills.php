<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tax we keep back from a supplier as a withholding agent: it does not change the bill total, only what the supplier is paid. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_bills', fn (Blueprint $t) => $t->decimal('tax_withheld', 14, 2)->default(0));
        Schema::table('supplier_bill_lines', fn (Blueprint $t) => $t->decimal('line_withheld', 14, 2)->default(0));
    }

    public function down(): void
    {
        Schema::table('supplier_bill_lines', fn (Blueprint $t) => $t->dropColumn('line_withheld'));
        Schema::table('supplier_bills', fn (Blueprint $t) => $t->dropColumn('tax_withheld'));
    }
};
