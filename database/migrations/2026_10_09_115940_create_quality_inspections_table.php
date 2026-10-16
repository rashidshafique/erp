
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('inspection_number', 50)->unique();

            $table->foreignUuid('casting_lot_id')
                ->constrained('casting_lots')
                ->restrictOnDelete();

            $table->foreignUuid('stage_definition_id')
                ->nullable()
                ->constrained('production_stage_definitions')
                ->restrictOnDelete();

            $table->string('inspection_type', 40)->default('FINAL');
            $table->string('status', 30)->default('PENDING');
            $table->string('overall_disposition', 30)->nullable();

            $table->unsignedInteger('sample_size')->default(0);
            $table->unsignedInteger('quantity_inspected')->default(0);
            $table->unsignedInteger('quantity_accepted')->default(0);
            $table->unsignedInteger('quantity_rejected')->default(0);
            $table->unsignedInteger('quantity_rework')->default(0);
            $table->unsignedInteger('quantity_pending')->default(0);

            $table->string('inspector_name', 150)->nullable();
            $table->timestampTz('inspected_at')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['casting_lot_id', 'inspected_at']);
            $table->index(['status', 'inspection_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_inspections');
    }
};