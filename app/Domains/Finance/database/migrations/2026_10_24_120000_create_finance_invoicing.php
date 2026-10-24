<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->decimal('rate_percent', 7, 3);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number', 40)->nullable()->unique();
            $table->string('kind', 20)->default('INVOICE');
            $table->string('status', 20)->default('DRAFT');
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->uuid('original_invoice_id')->nullable();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->string('customer_reference', 100)->nullable();
            $table->string('source_type', 30)->nullable();
            $table->uuid('source_id')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->string('void_reason', 300)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('original_invoice_id')->references('id')->on('invoices')->restrictOnDelete();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('description', 300);
            $table->decimal('quantity', 14, 3);
            $table->string('uom', 20)->nullable();
            $table->decimal('unit_price', 14, 4);
            $table->foreignUuid('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_percent', 7, 3)->default(0);
            $table->decimal('line_net', 14, 2);
            $table->decimal('line_tax', 14, 2)->default(0);
            $table->uuid('sales_order_line_id')->nullable();
            $table->uuid('dispatch_item_id')->nullable();
            $table->timestamps();

            $table->index('sales_order_line_id');
            $table->index('dispatch_item_id');
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receipt_number', 40)->unique();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->date('received_on');
            $table->decimal('amount', 14, 2);
            $table->string('method', 30)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'received_on']);
        });

        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->index('invoice_id');
        });

        $now = now();
        if (Schema::hasTable('roles') && ! DB::table('roles')->where('code', 'ACCOUNTS')->exists()) {
            $id = DB::table('roles')->insertGetId(['code' => 'ACCOUNTS', 'name' => 'Accounts', 'is_system' => false, 'created_at' => $now, 'updated_at' => $now]);
            foreach (['finance.manage', 'attachments.manage', 'audit.view'] as $p) {
                DB::table('role_permissions')->insert(['role_id' => $id, 'permission' => $p]);
            }
        }
    }

    public function down(): void
    {
        foreach (['receipt_allocations', 'receipts', 'invoice_lines', 'invoices', 'tax_rates'] as $t) {
            Schema::dropIfExists($t);
        }
        if (Schema::hasTable('roles')) {
            $id = DB::table('roles')->where('code', 'ACCOUNTS')->value('id');
            if ($id) {
                DB::table('role_permissions')->where('role_id', $id)->delete();
                DB::table('roles')->where('id', $id)->delete();
            }
        }
    }
};
