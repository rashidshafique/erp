
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_work_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('work_order_number', 40)->unique();

            $table->foreignUuid('equipment_id')
                ->constrained('equipment')
                ->restrictOnDelete();

            $table->foreignUuid('maintenance_plan_id')
                ->nullable()
                ->constrained('maintenance_plans')
                ->restrictOnDelete();

            $table->string('maintenance_type', 30);
            $table->string('priority', 20)->default('NORMAL');
            $table->string('status', 30)->default('OPEN');

            $table->text('problem_description')->nullable();
            $table->text('work_performed')->nullable();
            $table->text('findings')->nullable();

            $table->timestampTz('scheduled_start_at')->nullable();
            $table->timestampTz('scheduled_end_at')->nullable();
            $table->timestampTz('actual_start_at')->nullable();
            $table->timestampTz('actual_end_at')->nullable();

            $table->decimal('labor_hours', 10, 2)->nullable();
            $table->decimal('parts_cost', 14, 2)->nullable();
            $table->decimal('other_cost', 14, 2)->nullable();

            $table->string('assigned_to')->nullable();
            $table->string('completed_by')->nullable();

            $table->timestamps();

            $table->index(['equipment_id', 'status']);
            $table->index(['scheduled_start_at', 'scheduled_end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_work_orders');
    }
};