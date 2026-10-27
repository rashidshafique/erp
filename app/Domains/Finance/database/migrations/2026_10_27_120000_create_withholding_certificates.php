<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The certificate a customer gives for tax it kept back from our invoices and paid to the authority. It settles that part of
 * each invoice, so receivables stop counting it, and the tax becomes ours to claim. One certificate is for one tax.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withholding_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('certificate_number', 40)->unique();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('tax_code', 40);
            $table->string('tax_label', 120);
            $table->string('authority', 20)->nullable();
            $table->string('reference', 100);
            $table->date('certificate_date');
            $table->decimal('amount', 14, 2);
            $table->string('status', 10)->default('ACTIVE');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 300)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'certificate_date']);
        });
        DB::statement("ALTER TABLE withholding_certificates ADD CONSTRAINT withholding_certificates_status_valid CHECK (status IN ('ACTIVE','VOID'))");

        Schema::create('withholding_certificate_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('withholding_certificate_id')->constrained('withholding_certificates')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withholding_certificate_allocations');
        Schema::dropIfExists('withholding_certificates');
    }
};
