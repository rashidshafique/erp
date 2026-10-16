
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('production_order_id')
                ->constrained('production_orders')
                ->restrictOnDelete();

            $table->foreignUuid('operation_id')
                ->constrained('operations')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('sequence');

            $table->string('status', 30)->default('PENDING');

            $table->decimal('planned_qty', 14, 3);
            $table->decimal('started_qty', 14, 3)->default(0);
            $table->decimal('completed_qty', 14, 3)->default(0);
            $table->decimal('rejected_qty', 14, 3)->default(0);

            $table->timestampTz('planned_start_at')->nullable();
            $table->timestampTz('planned_end_at')->nullable();
            $table->timestampTz('actual_start_at')->nullable();
            $table->timestampTz('actual_end_at')->nullable();

            $table->string('assigned_to', 100)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['production_order_id', 'sequence'],
                'prod_order_operations_sequence_unique'
            );

            $table->index(['operation_id', 'status']);
            $table->index(['status', 'planned_start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_operations');
    }
};