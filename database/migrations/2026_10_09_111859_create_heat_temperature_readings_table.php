
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heat_temperature_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('heat_id')
                ->constrained('heats')
                ->restrictOnDelete();

            $table->decimal('temperature_c', 8, 2);
            $table->string('measurement_stage', 40);
            $table->timestampTz('measured_at');

            $table->string('instrument_type', 40)->nullable();
            $table->string('instrument_reference', 100)->nullable();
            $table->string('measured_by', 100)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(
                ['heat_id', 'measured_at'],
                'heat_temperature_readings_heat_time_index'
            );

            $table->index('measurement_stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heat_temperature_readings');
    }
};