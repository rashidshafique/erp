<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Bank details on a ledger account, and statements reconciled against it by ticking the journal lines that cleared. Journals are never touched. */
    public function up(): void
    {
        Schema::create('ledger_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->unique()->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('bank_name', 100);
            $table->string('branch', 100)->nullable();
            $table->string('account_title', 150)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('iban', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_account_id')->constrained('ledger_bank_accounts')->cascadeOnDelete();
            $table->date('statement_date');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('statement_balance', 14, 2);
            $table->string('status', 10)->default('DRAFT');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['bank_account_id', 'status', 'statement_date']);
        });

        Schema::create('ledger_reconciliation_lines', function (Blueprint $table) {
            $table->foreignUuid('reconciliation_id')->constrained('ledger_reconciliations')->cascadeOnDelete();
            $table->foreignUuid('journal_line_id')->unique()->constrained('ledger_journal_lines')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_reconciliation_lines');
        Schema::dropIfExists('ledger_reconciliations');
        Schema::dropIfExists('ledger_bank_accounts');
    }
};
