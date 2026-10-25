<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('lot_number', 30)->unique();
            $table->foreignUuid('core_definition_id')->constrained('core_definitions')->restrictOnDelete();
            $table->foreignUuid('core_box_id')->constrained('core_boxes')->restrictOnDelete();
            $table->unsignedInteger('quantity_made');
            $table->date('made_on');
            $table->string('location', 100)->nullable();
            $table->string('status', 20)->default('MADE');
            $table->string('qc_status', 20)->default('NOT_REQUIRED');
            $table->string('qc_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['core_definition_id', 'status']);
        });

        Schema::create('core_lot_uses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('core_lot_id')->constrained('core_lots')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('kind', 20)->default('USED');
            $table->foreignUuid('moulding_run_id')->nullable()->constrained('moulding_runs')->nullOnDelete();
            $table->date('used_on');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_lot_uses');
        Schema::dropIfExists('core_lots');
    }
};
