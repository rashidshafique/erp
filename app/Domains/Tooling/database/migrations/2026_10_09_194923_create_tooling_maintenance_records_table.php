<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tooling_maintenance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The tooling item being inspected, repaired, or modified.
            $table->string('tooling_type', 30);
            $table->foreignUuid('pattern_id')
                ->nullable()
                ->constrained('patterns')
                ->restrictOnDelete();

            $table->foreignUuid('pattern_revision_id')
                ->nullable()
                ->constrained('pattern_revisions')
                ->restrictOnDelete();

            $table->foreignUuid('core_definition_id')
                ->nullable()
                ->constrained('core_definitions')
                ->restrictOnDelete();

            $table->foreignUuid('core_box_id')
                ->nullable()
                ->constrained('core_boxes')
                ->restrictOnDelete();

            $table->foreignUuid('mould_configuration_id')
                ->nullable()
                ->constrained('mould_configurations')
                ->restrictOnDelete();

            $table->foreignUuid('mould_assembly_id')
                ->nullable()
                ->constrained('mould_assemblies')
                ->restrictOnDelete();

            // Maintenance record details.
            $table->string('record_number', 50)->unique();
            $table->string('activity_type', 30);
            $table->string('status', 20)->default('OPEN');
            $table->string('priority', 20)->default('NORMAL');

            $table->timestampTz('reported_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->string('reported_by')->nullable();
            $table->string('performed_by')->nullable();

            $table->text('problem_description')->nullable();
            $table->text('inspection_findings')->nullable();
            $table->text('work_performed')->nullable();
            $table->text('parts_used')->nullable();
            $table->text('notes')->nullable();

            // Costs and downtime.
            $table->decimal('labour_hours', 10, 2)->default(0);
            $table->decimal('parts_cost', 14, 2)->default(0);
            $table->decimal('other_cost', 14, 2)->default(0);
            $table->decimal('downtime_hours', 10, 2)->default(0);

            // Optional follow-up scheduling.
            $table->date('next_inspection_due_on')->nullable();

            $table->timestampsTz();

            $table->index(['tooling_type', 'activity_type']);
            $table->index(['status', 'priority']);
            $table->index('completed_at');
            $table->index('next_inspection_due_on');
        });
        DB::statement('
            ALTER TABLE tooling_maintenance_records
            ADD CONSTRAINT tooling_maintenance_records_exactly_one_tooling_check
            CHECK (
                (CASE WHEN pattern_id IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN pattern_revision_id IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN core_definition_id IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN core_box_id IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN mould_configuration_id IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN mould_assembly_id IS NOT NULL THEN 1 ELSE 0 END)
                = 1
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('tooling_maintenance_records');
    }
};
