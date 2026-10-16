<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('furnaces', function (Blueprint $table) {
            $table->decimal('max_operating_power_kw', 12, 3)
                ->nullable();
        });

        Schema::table('heats', function (Blueprint $table) {
            $table->decimal('operating_power_limit_kw', 12, 3)
                ->nullable();
        });

        Schema::create('heat_power_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('heat_id')
                ->constrained('heats')
                ->restrictOnDelete();

            $table->unsignedInteger('reading_number');

            $table->timestampTz('recorded_at');
            $table->decimal('actual_power_kw', 12, 3)->nullable();
            $table->decimal('cumulative_energy_kwh', 16, 3)->nullable();

            $table->string('source', 20)->default('MANUAL');
            $table->string('recorded_by', 100)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['heat_id', 'reading_number'],
                'heat_power_readings_heat_number_unique'
            );

            $table->index(['heat_id', 'recorded_at']);
        });

        Schema::create('heat_process_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('heat_id')
                ->constrained('heats')
                ->restrictOnDelete();

            $table->unsignedInteger('reading_number');
            $table->string('parameter_code', 60);
            $table->decimal('numeric_value', 14, 4);
            $table->string('unit', 30);
            $table->timestampTz('recorded_at');

            $table->string('source', 20)->default('MANUAL');
            $table->string('recorded_by', 100)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['heat_id', 'reading_number'],
                'heat_process_readings_heat_number_unique'
            );

            $table->index(
                ['heat_id', 'parameter_code', 'recorded_at'],
                'heat_process_readings_parameter_time_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heat_process_readings');
        Schema::dropIfExists('heat_power_readings');

        Schema::table('heats', function (Blueprint $table) {
            $table->dropColumn('operating_power_limit_kw');
        });

        Schema::table('furnaces', function (Blueprint $table) {
            $table->dropColumn('max_operating_power_kw');
        });
    }
};
