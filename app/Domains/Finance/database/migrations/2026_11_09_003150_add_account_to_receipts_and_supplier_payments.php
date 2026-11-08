<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The cash or bank account money came into or went out of (design 24d "Into"). No foreign key: the ledger is its own module.
     * Rows already posted take the account their journal moved, so nothing posted changes; the rest are filled when they post.
     */
    public function up(): void
    {
        // The cash or bank line is the journal's one line without a party: a receipt's debit, a payment's credit.
        foreach (['receipts' => ['receipt', 'debit'], 'supplier_payments' => ['supplier_payment', 'credit']] as $table => [$source, $side]) {
            Schema::table($table, function (Blueprint $t) {
                $t->uuid('account_id')->nullable()->after('amount');
            });
            if (Schema::hasTable('ledger_journal_lines')) {
                DB::statement("UPDATE {$table} SET account_id = (SELECT l.account_id FROM ledger_journals j JOIN ledger_journal_lines l ON l.journal_id = j.id
                    WHERE j.source_type = ? AND j.source_id = {$table}.id AND j.kind = 'POST' AND l.party_type IS NULL AND l.{$side} > 0 LIMIT 1)", [$source]);
            }
        }
    }

    public function down(): void
    {
        foreach (['receipts', 'supplier_payments'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('account_id');
            });
        }
    }
};
