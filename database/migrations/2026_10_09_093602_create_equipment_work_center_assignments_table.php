
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_work_center_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('equipment_id')
                ->constrained('equipment')
                ->cascadeOnDelete();

            $table->foreignUuid('work_center_id')
                ->constrained('work_centers')
                ->restrictOnDelete();

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index([
                'equipment_id',
                'effective_from',
                'effective_to'
            ]);

            $table->index([
                'work_center_id',
                'effective_from',
                'effective_to'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_work_center_assignments');
    }
};