
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nonconformance_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('ncr_number', 50)->unique();

            $table->foreignUuid('casting_lot_id')
                ->constrained('casting_lots')
                ->restrictOnDelete();

            $table->foreignUuid('quality_inspection_id')
                ->nullable()
                ->constrained('quality_inspections')
                ->restrictOnDelete();

            $table->foreignUuid('quality_defect_id')
                ->nullable()
                ->constrained('quality_defects')
                ->restrictOnDelete();

            $table->string('title', 200);
            $table->text('description');

            $table->string('severity', 20)->default('MINOR');
            $table->string('status', 30)->default('OPEN');
            $table->string('disposition', 30)->nullable();

            $table->unsignedInteger('quantity_affected')->default(0);

            $table->string('reported_by', 150)->nullable();
            $table->timestampTz('reported_at')->useCurrent();

            $table->text('containment_action')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->text('preventive_action')->nullable();

            $table->string('assigned_to', 150)->nullable();
            $table->timestampTz('target_completion_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->string('closed_by', 150)->nullable();

            $table->text('verification_notes')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['casting_lot_id', 'status']);
            $table->index(['severity', 'status']);
            $table->index(['target_completion_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nonconformance_reports');
    }
};