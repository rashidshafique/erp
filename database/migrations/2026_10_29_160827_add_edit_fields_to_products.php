<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the edit product screen (design 10f) keeps: the product's status and customer, and on each revision what the customer
 * calls it, what it is approved for, the PPAP level and the commercial terms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status', 20)->default('ACTIVE')->after('active');
            $table->foreignUuid('customer_id')->nullable()->after('status')->constrained('customers')->nullOnDelete();
        });
        DB::table('products')->where('active', false)->update(['status' => 'OBSOLETE']);

        Schema::table('product_revisions', function (Blueprint $table) {
            $table->string('customer_part_number', 80)->nullable();
            $table->string('csr', 120)->nullable();
            $table->string('approved_for', 20)->nullable();
            $table->unsignedTinyInteger('ppap_level')->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->unsignedInteger('annual_volume')->nullable();
            $table->string('packing_spec', 80)->nullable();
            $table->string('default_delivery', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_revisions', function (Blueprint $table) {
            $table->dropColumn(['customer_part_number', 'csr', 'approved_for', 'ppap_level', 'unit_price', 'annual_volume', 'packing_spec', 'default_delivery']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('status');
        });
    }
};
