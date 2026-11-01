<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The product an invoice line was picked from, if any. A line can still be free text; the description is what prints. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignUuid('product_id')->nullable()->after('line_number')->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_id'));
    }
};
