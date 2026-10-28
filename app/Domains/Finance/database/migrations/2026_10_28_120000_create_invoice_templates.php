<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Print layouts built by finance staff: a name, what they are for, and the layout as data (see InvoiceLayouts). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('applies_to', 12)->default('BOTH');
            $table->json('layout');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        // a custom layout's key is "custom:" and its id, which is longer than the first 40 characters allowed
        Schema::table('invoices', fn (Blueprint $table) => $table->string('print_template', 60)->nullable()->change());
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
        Schema::table('invoices', fn (Blueprint $table) => $table->string('print_template', 40)->nullable()->change());
    }
};
