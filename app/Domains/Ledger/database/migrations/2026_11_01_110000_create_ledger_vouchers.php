<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vouchers (design 19b): a journal carries its voucher type (JV, CPV, BPV, CRV, BRV; none for one posted under a document's own
 * number), a reference such as a cheque number, and the cash or bank account a cash or bank voucher balances against. A voucher
 * saved but not posted is a draft: it holds its number and lines but never counts in the books, so it lives in its own table and
 * takes the same id when it is posted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_journals', function (Blueprint $table) {
            $table->string('voucher_type', 10)->nullable()->after('kind');
            $table->string('reference', 100)->nullable()->after('memo');
            $table->uuid('counter_account_id')->nullable()->after('reference');
            $table->index('voucher_type');
        });

        Schema::create('ledger_vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique();
            $table->string('voucher_type', 10);
            $table->date('voucher_date');
            $table->uuid('counter_account_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->json('lines');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_vouchers');
        Schema::table('ledger_journals', function (Blueprint $table) {
            $table->dropIndex(['voucher_type']);
            $table->dropColumn(['voucher_type', 'reference', 'counter_account_id']);
        });
    }
};
