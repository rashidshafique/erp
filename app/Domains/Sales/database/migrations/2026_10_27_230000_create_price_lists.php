<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Price lists: a product's price per piece or per kg, with quantity breaks, valid between dates. A customer can be given its
 * own list; the default list covers everyone else and fills the gaps in a customer's list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('currency', 10)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('basis', 10)->default('PIECE');
            $table->decimal('min_quantity', 14, 3)->default(0);
            $table->decimal('price', 14, 4);
            $table->timestamps();

            $table->unique(['price_list_id', 'product_id', 'basis', 'min_quantity']);
        });
        DB::statement("ALTER TABLE price_list_items ADD CONSTRAINT price_list_items_basis_valid CHECK (basis IN ('PIECE','KG'))");

        Schema::table('customers', function (Blueprint $t) {
            $t->foreignUuid('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropConstrainedForeignId('price_list_id'));
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};
