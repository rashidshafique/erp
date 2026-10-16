<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tooling_usage_records', function (Blueprint $table) {
            $table->uuid('id')->primary();

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

            $table->string('tooling_type', 30);
            $table->decimal('usage_quantity', 12, 3)->default(1);
            $table->string('usage_unit', 30)->default('CYCLES');

            $table->timestamp('used_at');
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();

            $table->string('recorded_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tooling_type', 'used_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        DB::statement("
            ALTER TABLE tooling_usage_records
            ADD CONSTRAINT tooling_usage_records_exactly_one_tooling_check
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
            ALTER TABLE tooling_usage_records
            ADD CONSTRAINT tooling_usage_records_positive_quantity_check
            CHECK (usage_quantity > 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('tooling_usage_records');
    }
};