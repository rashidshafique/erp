
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_meter_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('equipment_meter_id')
                ->constrained('equipment_meters')
                ->restrictOnDelete();

            $table->decimal('reading_value', 16, 3);
            $table->timestampTz('recorded_at');

            $table->string('reading_source', 30)->default('MANUAL');
            $table->string('recorded_by')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['equipment_meter_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_meter_readings');
    }
};