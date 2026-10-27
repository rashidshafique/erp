<?php

use App\Domains\Ledger\Services\AdvanceReclass;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Receipts and supplier payments are now split between receivables or payables and the advance accounts (IAS 1: no netting).
 * Money on account from before is moved once, dated today. Nothing happens if the chart lacks an advance account; the same move
 * runs again from Settings > Ledger accounts > Post what is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ledger_journals')) {
            return;
        }
        try {
            app(AdvanceReclass::class)->run();
        } catch (InvalidArgumentException) {
            // no advance account in the chart, or the books are closed: left for "Post what is missing"
        }
    }

    public function down(): void {}
};
