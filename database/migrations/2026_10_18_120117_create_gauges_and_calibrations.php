<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gauges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('instrument_type', 30)->default('OTHER');
            $table->string('manufacturer', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('range_description', 150)->nullable();
            $table->string('location', 150)->nullable();
            $table->string('responsible_person', 150)->nullable();
            $table->string('status', 20)->default('IN_SERVICE');
            $table->boolean('requires_calibration')->default(true);
            $table->unsignedSmallInteger('calibration_interval_days')->nullable();
            $table->date('last_calibrated_on')->nullable();
            $table->date('next_due_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'next_due_on']);
        });

        Schema::create('gauge_calibrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('gauge_id')->constrained('gauges')->cascadeOnDelete();
            $table->date('calibrated_on');
            $table->date('due_on')->nullable();
            $table->string('result', 12);
            $table->string('performed_by', 150)->nullable();
            $table->boolean('external_lab')->default(false);
            $table->string('certificate_number', 100)->nullable();
            $table->text('as_found')->nullable();
            $table->text('notes')->nullable();
            $table->string('recorded_by', 150)->nullable();
            $table->timestamps();
            $table->index(['gauge_id', 'calibrated_on']);
        });

        Schema::table('quality_inspection_results', function (Blueprint $table) {
            $table->foreignUuid('gauge_id')->nullable()->constrained('gauges')->nullOnDelete();
        });
        Schema::table('heat_chemistry_readings', function (Blueprint $table) {
            $table->foreignUuid('gauge_id')->nullable()->constrained('gauges')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('heat_chemistry_readings', fn (Blueprint $table) => $table->dropConstrainedForeignId('gauge_id'));
        Schema::table('quality_inspection_results', fn (Blueprint $table) => $table->dropConstrainedForeignId('gauge_id'));
        Schema::dropIfExists('gauge_calibrations');
        Schema::dropIfExists('gauges');
    }
};
