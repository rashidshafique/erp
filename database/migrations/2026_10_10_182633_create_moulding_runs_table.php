<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moulding_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('run_number', 80)->unique();

            $table->foreignUuid('production_order_id')
                ->constrained('production_orders')
                ->restrictOnDelete();

            $table->foreignUuid('pattern_revision_id')
                ->nullable()
                ->constrained('pattern_revisions')
                ->restrictOnDelete();

            $table->foreignUuid('mould_configuration_id')
                ->nullable()
                ->constrained('mould_configurations')
                ->restrictOnDelete();

            $table->date('run_date');
            $table->string('shift', 30)->nullable();

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->string('moulding_method', 40)->nullable();
            $table->string('sand_system', 60)->nullable();

            $table->string('operator_name', 100)->nullable();
            $table->string('team_name', 100)->nullable();

            $table->unsignedInteger('planned_mould_qty')->default(0);
            $table->unsignedInteger('moulds_made_qty')->default(0);
            $table->unsignedInteger('accepted_mould_qty')->default(0);
            $table->unsignedInteger('damaged_mould_qty')->default(0);

            $table->unsignedInteger('downtime_minutes')->default(0);
            $table->string('downtime_reason', 150)->nullable();

            $table->string('status', 30)->default('IN_PROGRESS');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['run_date', 'shift']);
            $table->index(['production_order_id', 'status']);
            $table->index(['pattern_revision_id', 'run_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moulding_runs');
    }
};
