<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('material_lots', function (Blueprint $table) {
        $table->uuid('id')->primary();

        $table->string('lot_number', 80)->unique();

        $table->foreignUuid('material_id')
            ->constrained('materials')
            ->restrictOnDelete();

        $table->string('supplier_lot_number', 100)->nullable();
        $table->string('supplier_name_snapshot', 150)->nullable();

        $table->date('received_date')->nullable();
        $table->date('manufacture_date')->nullable();
        $table->date('expiry_date')->nullable();

        $table->string('status', 30)->default('QUARANTINE');
        $table->decimal('received_qty', 14, 3)->default(0);
        $table->decimal('accepted_qty', 14, 3)->default(0);

        $table->decimal('unit_cost', 14, 4)->nullable();
        $table->string('currency_code', 3)->default('PKR');

        $table->string('heat_or_batch_reference', 100)->nullable();
        $table->text('certificate_reference')->nullable();
        $table->text('notes')->nullable();

        $table->timestamps();

        $table->index(['material_id', 'status']);
        $table->index('received_date');
        $table->index('supplier_lot_number');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_lots');
    }
};
