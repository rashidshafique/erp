
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_capability_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('equipment_id')
                ->constrained('equipment')
                ->cascadeOnDelete();

            $table->foreignUuid('equipment_capability_id')
                ->constrained('equipment_capabilities')
                ->restrictOnDelete();

            $table->string('status', 20)->default('APPROVED');

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'equipment_id',
                'equipment_capability_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_capability_assignments');
    }
};