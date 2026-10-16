
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tooling_life_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('tooling_type', 30);

            $table->foreignUuid('pattern_id')
                ->nullable()->constrained('patterns')->restrictOnDelete();

            $table->foreignUuid('pattern_revision_id')
                ->nullable()->constrained('pattern_revisions')->restrictOnDelete();

            $table->foreignUuid('core_definition_id')
                ->nullable()->constrained('core_definitions')->restrictOnDelete();

            $table->foreignUuid('core_box_id')
                ->nullable()->constrained('core_boxes')->restrictOnDelete();

            $table->foreignUuid('mould_configuration_id')
                ->nullable()->constrained('mould_configurations')->restrictOnDelete();

            $table->foreignUuid('mould_assembly_id')
                ->nullable()->constrained('mould_assemblies')->restrictOnDelete();

            $table->string('usage_unit', 30)->default('CYCLES');

            $table->decimal('service_life_quantity', 12, 3)->nullable();
            $table->decimal('inspection_interval_quantity', 12, 3)->nullable();

            // Cumulative usage recorded at the last completed inspection.
            $table->decimal('usage_at_last_inspection', 14, 3)->default(0);
            $table->timestamp('last_inspected_at')->nullable();

            $table->unsignedSmallInteger('warning_threshold_percent')->default(80);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tooling_type', 'is_active']);
        });

        DB::statement("
            ALTER TABLE tooling_life_limits
            ADD CONSTRAINT tooling_life_limits_exactly_one_tooling_check
            CHECK (
                num_nonnulls(
                    pattern_id,
                    pattern_revision_id,
                    core_definition_id,
                    core_box_id,
                    mould_configuration_id,
                    mould_assembly_id
                ) = 1
            )
        ");

        DB::statement("
            ALTER TABLE tooling_life_limits
            ADD CONSTRAINT tooling_life_limits_positive_quantities_check
            CHECK (
                (service_life_quantity IS NULL OR service_life_quantity > 0)
                AND
                (inspection_interval_quantity IS NULL OR inspection_interval_quantity > 0)
                AND
                usage_at_last_inspection >= 0
                AND
                warning_threshold_percent BETWEEN 1 AND 100
            )
        ");

        // A tooling item can have at most one active configuration row.
        foreach ([
            'pattern_id',
            'pattern_revision_id',
            'core_definition_id',
            'core_box_id',
            'mould_configuration_id',
            'mould_assembly_id',
        ] as $column) {
            DB::statement(
                "CREATE UNIQUE INDEX tooling_life_limits_{$column}_unique
                 ON tooling_life_limits ({$column})
                 WHERE {$column} IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tooling_life_limits');
    }
};