
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_deviations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('deviation_number', 100)->unique();

            $table->foreignUuid('product_specification_id')
                ->nullable()
                ->constrained('product_specifications')
                ->restrictOnDelete();

            $table->foreignUuid('customer_product_requirement_id')
                ->nullable()
                ->constrained('customer_product_requirements')
                ->restrictOnDelete();

            $table->foreignUuid('production_order_id')
                ->nullable()
                ->constrained('production_orders')
                ->restrictOnDelete();

            $table->foreignUuid('casting_lot_id')
                ->nullable()
                ->constrained('casting_lots')
                ->restrictOnDelete();

            $table->string('deviation_type', 40)->default('TEMPORARY');
            $table->string('status', 30)->default('DRAFT');
            $table->string('severity', 20)->default('MAJOR');

            $table->string('characteristic_code', 100)->nullable();
            $table->string('characteristic_name', 200)->nullable();

            $table->text('requirement_description');
            $table->text('requested_exception');
            $table->text('technical_justification')->nullable();
            $table->text('risk_assessment')->nullable();
            $table->text('compensating_controls')->nullable();

            $table->decimal('quantity_affected', 14, 3)->nullable();
            $table->string('quantity_uom', 10)->nullable();

            $table->string('requested_by', 150)->nullable();
            $table->string('approved_by', 150)->nullable();
            $table->string('customer_approval_reference', 150)->nullable();

            $table->timestampTz('requested_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('effective_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('closed_at')->nullable();

            $table->text('disposition')->nullable();
            $table->text('closure_notes')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['production_order_id', 'status']);
            $table->index(['casting_lot_id', 'status']);
            $table->index(['product_specification_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_deviations');
    }
};