<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Selling, scrapping or writing off an asset (design 28h): what it fetched, its depreciation to the day, and the cost and
     * depreciation it took off the books, kept for the roll-forward (28i).
     */
    public function up(): void
    {
        Schema::create('fixed_asset_disposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fixed_asset_id')->unique()->constrained('fixed_assets')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->date('disposed_on');
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignUuid('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('weight_kg', 12, 2)->nullable();
            $table->decimal('charge', 15, 2)->default(0);
            $table->decimal('cost_removed', 15, 2);
            $table->decimal('depreciation_cleared', 15, 2);
            $table->decimal('book_value', 15, 2);
            $table->decimal('gain', 15, 2);
            $table->foreignUuid('journal_id')->nullable()->constrained('ledger_journals')->nullOnDelete();
            $table->string('reason', 300)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('disposed_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_disposals');
    }
};
