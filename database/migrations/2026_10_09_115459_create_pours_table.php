
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pours', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('pour_number', 80)->unique();

            $table->foreignUuid('heat_id')
                ->constrained('heats')
                ->restrictOnDelete();

            $table->string('status', 30)->default('PLANNED');

            $table->timestampTz('planned_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->decimal('pour_temperature_c', 8, 2)->nullable();
            $table->decimal('total_poured_weight_kg', 14, 3)->nullable();

            $table->string('operator_name', 100)->nullable();
            $table->string('ladle_reference', 80)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['heat_id', 'started_at']);
            $table->index(['status', 'planned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pours');
    }
};