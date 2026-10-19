<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->string('certificate_type', 12)->nullable();
        });

        Schema::create('material_inspection_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('material_id')->constrained('materials')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('characteristic', 120);
            $table->string('method', 150)->nullable();
            $table->decimal('min_value', 14, 4)->nullable();
            $table->decimal('max_value', 14, 4)->nullable();
            $table->string('unit', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('incoming_inspections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('material_lot_id')->constrained('material_lots')->cascadeOnDelete();
            $table->date('inspected_on');
            $table->string('result', 12);
            $table->string('inspector', 150)->nullable();
            $table->foreignUuid('gauge_id')->nullable()->constrained('gauges')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['material_lot_id', 'inspected_on']);
        });

        Schema::create('incoming_inspection_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('incoming_inspection_id')->constrained('incoming_inspections')->cascadeOnDelete();
            $table->string('characteristic', 120);
            $table->string('method', 150)->nullable();
            $table->decimal('min_value', 14, 4)->nullable();
            $table->decimal('max_value', 14, 4)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('measured_value', 14, 4);
            $table->boolean('passed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_inspection_results');
        Schema::dropIfExists('incoming_inspections');
        Schema::dropIfExists('material_inspection_items');
        Schema::table('material_lots', fn (Blueprint $table) => $table->dropColumn('certificate_type'));
    }
};
