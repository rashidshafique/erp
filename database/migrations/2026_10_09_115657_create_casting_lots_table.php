
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casting_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('lot_number', 80)->unique();

            $table->foreignUuid('pour_mould_assembly_id')
                ->constrained('pour_mould_assemblies')
                ->restrictOnDelete();

            $table->foreignUuid('production_order_id')
                ->constrained('production_orders')
                ->restrictOnDelete();

            $table->foreignUuid('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignUuid('product_revision_id')
                ->constrained('product_revisions')
                ->restrictOnDelete();

            $table->string('status', 30)->default('IN_PRODUCTION');

            $table->unsignedInteger('quantity_poured')->default(0);
            $table->unsignedInteger('quantity_good')->default(0);
            $table->unsignedInteger('quantity_rejected')->default(0);

            $table->decimal('gross_weight_kg', 14, 3)->nullable();
            $table->decimal('net_casting_weight_kg', 14, 3)->nullable();

            $table->timestampTz('created_from_pour_at')->nullable();
            $table->timestampTz('closed_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['production_order_id', 'status']);
            $table->index(['product_id', 'product_revision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casting_lots');
    }
};