<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bill_number', 40)->nullable()->unique();
            $table->string('supplier_bill_number', 100)->nullable();
            $table->string('kind', 20)->default('BILL');
            $table->string('status', 20)->default('DRAFT');
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->uuid('original_bill_id')->nullable();
            $table->uuid('purchase_order_id')->nullable();
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->string('void_reason', 300)->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index('purchase_order_id');
        });

        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->foreign('original_bill_id')->references('id')->on('supplier_bills')->restrictOnDelete();
        });

        Schema::create('supplier_bill_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_bill_id')->constrained('supplier_bills')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('description', 300);
            $table->decimal('quantity', 14, 3);
            $table->string('uom', 20)->nullable();
            $table->decimal('unit_price', 14, 4);
            $table->decimal('tax_percent', 7, 3)->default(0);
            $table->decimal('line_net', 14, 2);
            $table->decimal('line_tax', 14, 2)->default(0);
            $table->uuid('purchase_order_line_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('payment_number', 40)->unique();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 14, 2);
            $table->string('method', 30)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'paid_on']);
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_payment_id')->constrained('supplier_payments')->cascadeOnDelete();
            $table->foreignUuid('supplier_bill_id')->constrained('supplier_bills')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->index('supplier_bill_id');
        });
    }

    public function down(): void
    {
        foreach (['supplier_payment_allocations', 'supplier_payments', 'supplier_bill_lines', 'supplier_bills'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
