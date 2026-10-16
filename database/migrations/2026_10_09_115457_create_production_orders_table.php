
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('production_order_number', 50)->unique();

            $table->foreignUuid('sales_order_line_id')
                ->nullable()
                ->constrained('sales_order_lines')
                ->restrictOnDelete();

            $table->foreignUuid('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignUuid('product_revision_id')
                ->constrained('product_revisions')
                ->restrictOnDelete();

            $table->foreignUuid('manufacturing_method_id')
                ->nullable()
                ->constrained('manufacturing_methods')
                ->restrictOnDelete();

            $table->foreignUuid('process_route_id')
                ->nullable()
                ->constrained('process_routes')
                ->restrictOnDelete();

            $table->string('order_type', 30)->default('CUSTOMER');
            $table->string('status', 30)->default('DRAFT');
            $table->string('priority', 20)->default('NORMAL');

            $table->decimal('planned_qty', 14, 3);
            $table->decimal('released_qty', 14, 3)->default(0);
            $table->decimal('completed_qty', 14, 3)->default(0);
            $table->decimal('rejected_qty', 14, 3)->default(0);

            $table->string('uom', 20)->default('piece');

            $table->date('planned_start_date')->nullable();
            $table->date('planned_finish_date')->nullable();
            $table->date('required_date')->nullable();

            $table->timestampTz('actual_started_at')->nullable();
            $table->timestampTz('actual_completed_at')->nullable();

            $table->text('instructions')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority']);
            $table->index(['planned_start_date', 'planned_finish_date']);
            $table->index('sales_order_line_id');
            $table->index(['product_id', 'product_revision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};