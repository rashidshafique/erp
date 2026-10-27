<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a bill line is for tax (goods or a service) and the tax the supplier charged on it, named by tax and authority as the
 * country's rules see it, kept as it was worked out. Lines from before keep one unnamed tax at their percent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_bill_lines', function (Blueprint $t) {
            $t->string('supply_type', 10)->default('GOODS');
            $t->jsonb('taxes')->nullable();
        });
        DB::statement("ALTER TABLE supplier_bill_lines ADD CONSTRAINT supplier_bill_lines_supply_type_valid CHECK (supply_type IN ('GOODS','SERVICE'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE supplier_bill_lines DROP CONSTRAINT IF EXISTS supplier_bill_lines_supply_type_valid');
        Schema::table('supplier_bill_lines', fn (Blueprint $t) => $t->dropColumn(['supply_type', 'taxes']));
    }
};
