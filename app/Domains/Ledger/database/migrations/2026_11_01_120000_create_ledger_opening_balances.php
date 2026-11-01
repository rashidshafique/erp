<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Balances typed at go-live (design 19f), one per account, until they are locked into the opening voucher. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_opening_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->unique()->constrained('ledger_accounts')->cascadeOnDelete();
            $table->decimal('debit', 16, 2)->default(0);
            $table->decimal('credit', 16, 2)->default(0);
            $table->string('note', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_opening_balances');
    }
};
