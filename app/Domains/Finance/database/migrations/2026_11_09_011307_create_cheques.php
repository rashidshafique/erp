<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Post-dated cheques: received from customers or given to suppliers, dated after the day they change hands. Held until they
     * clear, or settled at once through a holding account, by the company's setting at the time. A receipt or payment that a
     * bounced cheque had settled is voided, not deleted.
     */
    public function up(): void
    {
        Schema::create('cheques', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('direction', 10); // RECEIVED, ISSUED
            $table->string('mode', 10); // HOLD, SETTLE
            $table->string('status', 12)->default('IN_HAND'); // IN_HAND, DEPOSITED, CLEARED, BOUNCED
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->string('cheque_number', 100)->nullable();
            $table->string('method', 30)->nullable();
            $table->string('drawn_on', 100)->nullable();
            $table->date('handed_on');
            $table->date('cheque_date');
            $table->decimal('amount', 14, 2);
            $table->uuid('account_id')->nullable();
            $table->json('allocations')->nullable();
            $table->uuid('sales_order_id')->nullable();
            $table->uuid('purchase_order_id')->nullable();
            $table->foreignUuid('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->foreignUuid('supplier_payment_id')->nullable()->constrained('supplier_payments')->nullOnDelete();
            $table->date('deposited_on')->nullable();
            $table->date('cleared_on')->nullable();
            $table->date('bounced_on')->nullable();
            $table->string('bounce_reason', 300)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['direction', 'status', 'cheque_date']);
        });

        foreach (['receipts', 'supplier_payments'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->uuid('cheque_id')->nullable()->after('account_id');
                $table->timestamp('voided_at')->nullable();
                $table->string('void_reason', 300)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['receipts', 'supplier_payments'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropColumn(['cheque_id', 'voided_at', 'void_reason']);
            });
        }
        Schema::dropIfExists('cheques');
    }
};
