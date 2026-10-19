
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('dispatch_number', 50)->unique();

            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->string('status', 30)->default('DRAFT');

            $table->timestampTz('planned_dispatch_at')->nullable();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();

            $table->string('delivery_challan_number', 100)->nullable();
            $table->string('invoice_number', 100)->nullable();

            $table->string('carrier_name', 150)->nullable();
            $table->string('vehicle_number', 50)->nullable();
            $table->string('driver_name', 150)->nullable();
            $table->string('tracking_reference', 150)->nullable();

            $table->text('delivery_address')->nullable();
            $table->string('dispatched_by', 150)->nullable();
            $table->string('received_by', 150)->nullable();

            $table->decimal('total_net_weight_kg', 12, 3)->default(0);
            $table->unsignedInteger('total_packages')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'dispatched_at']);
            $table->index(['status', 'planned_dispatch_at']);
            $table->index('delivery_challan_number');
            $table->index('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatches');
    }
};