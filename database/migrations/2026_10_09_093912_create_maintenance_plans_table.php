
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('equipment_id')
                ->constrained('equipment')
                ->restrictOnDelete();

            $table->string('code', 40)->unique();
            $table->string('name');

            $table->string('maintenance_type', 30);
            // PREVENTIVE, INSPECTION, LUBRICATION, CALIBRATION

            $table->string('trigger_type', 20);
            // CALENDAR, METER, BOTH

            $table->unsignedInteger('interval_days')->nullable();
            $table->decimal('interval_meter_units', 16, 3)->nullable();

            $table->decimal('estimated_duration_hours', 10, 2)->nullable();

            $table->boolean('requires_shutdown')->default(true);
            $table->boolean('active')->default(true);

            $table->timestampTz('last_completed_at')->nullable();
            $table->timestampTz('next_due_at')->nullable();
            $table->decimal('last_completed_meter', 16, 3)->nullable();
            $table->decimal('next_due_meter', 16, 3)->nullable();

            $table->text('instructions')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plans');
    }
};