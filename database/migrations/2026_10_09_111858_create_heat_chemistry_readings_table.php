
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heat_chemistry_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('heat_id')
                ->constrained('heats')
                ->restrictOnDelete();

            $table->unsignedInteger('reading_number');
            $table->string('sample_stage', 40)->default('AFTER_TREATMENT');
            $table->string('test_method', 40)->default('SPECTROMETER');

            // Percentages: 3.5000 means 3.5%.
            $table->decimal('carbon_percent', 7, 4)->nullable();
            $table->decimal('silicon_percent', 7, 4)->nullable();
            $table->decimal('manganese_percent', 7, 4)->nullable();
            $table->decimal('sulphur_percent', 7, 4)->nullable();
            $table->decimal('phosphorus_percent', 7, 4)->nullable();
            $table->decimal('chromium_percent', 7, 4)->nullable();
            $table->decimal('copper_percent', 7, 4)->nullable();
            $table->decimal('molybdenum_percent', 7, 4)->nullable();
            $table->decimal('magnesium_percent', 7, 4)->nullable();

            $table->decimal('carbon_equivalent', 7, 4)->nullable();

            $table->boolean('is_accepted')->nullable();
            $table->boolean('is_verified')->default(false);

            $table->string('instrument_reference', 100)->nullable();
            $table->string('sample_reference', 100)->nullable();
            $table->string('tested_by', 100)->nullable();
            $table->timestampTz('tested_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['heat_id', 'reading_number'],
                'heat_chemistry_readings_heat_number_unique'
            );

            $table->index(['heat_id', 'tested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heat_chemistry_readings');
    }
};