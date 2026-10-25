<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What an invoice line is for tax (goods or a service, its tariff or service code, its inventory class) and every tax on it, kept
 * as it was worked out so later rate changes never alter it. Withheld tax is kept back by the buyer for the authority: it does not
 * change the invoice total, only what the buyer pays the seller. The place of supply decides which region taxes a service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->string('place_of_supply', 10)->nullable();
            $t->decimal('tax_withheld', 14, 2)->default(0);
        });
        Schema::table('invoice_lines', function (Blueprint $t) {
            $t->string('supply_type', 10)->default('GOODS');
            $t->string('tariff_code', 20)->nullable();
            $t->string('inventory_class', 20)->nullable();
            $t->jsonb('taxes')->nullable();
            $t->decimal('line_withheld', 14, 2)->default(0);
        });
        DB::statement("ALTER TABLE invoice_lines ADD CONSTRAINT invoice_lines_supply_type_valid CHECK (supply_type IN ('GOODS','SERVICE'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE invoice_lines DROP CONSTRAINT IF EXISTS invoice_lines_supply_type_valid');
        Schema::table('invoice_lines', fn (Blueprint $t) => $t->dropColumn(['supply_type', 'tariff_code', 'inventory_class', 'taxes', 'line_withheld']));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['place_of_supply', 'tax_withheld']));
    }
};
