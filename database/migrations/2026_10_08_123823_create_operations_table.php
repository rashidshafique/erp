<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('process_route_id')
                ->constrained('process_routes')
                ->cascadeOnDelete();

            $table->foreignUuid('work_center_id')
                ->constrained('work_centers')
                ->restrictOnDelete();

            $table->unsignedInteger('sequence');

            $table->string('code', 40);
            $table->string('name');

            $table->string('operation_type', 40);

            $table->decimal('setup_time_minutes', 10, 2)->default(0);
            $table->decimal('run_time_minutes', 10, 4)->nullable();

            $table->decimal('queue_time_minutes', 10, 2)->default(0);
            $table->decimal('move_time_minutes', 10, 2)->default(0);

            $table->decimal('standard_labor_hours', 10, 4)->nullable();
            $table->decimal('standard_machine_hours', 10, 4)->nullable();

            $table->boolean('is_subcontracted')->default(false);
            $table->boolean('is_optional')->default(false);

            $table->text('instructions')->nullable();

            $table->timestamps();

            $table->unique([
                'process_route_id',
                'sequence'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};