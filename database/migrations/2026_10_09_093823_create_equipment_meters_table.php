
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_meters', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('equipment_id')
                ->constrained('equipment')
                ->restrictOnDelete();

            $table->string('meter_code', 40);
            $table->string('name');

            $table->string('unit', 20); // HOURS, CYCLES, KWH, etc.
            $table->decimal('current_reading', 16, 3)->default(0);

            $table->boolean('is_cumulative')->default(true);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['equipment_id', 'meter_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_meters');
    }
};