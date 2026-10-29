<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A product can have its own route instead of the foundry default: one PRODUCT route per product. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_routes', function (Blueprint $table) {
            $table->foreignUuid('product_id')->nullable()->after('applies_to')->unique()->constrained('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('process_routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
