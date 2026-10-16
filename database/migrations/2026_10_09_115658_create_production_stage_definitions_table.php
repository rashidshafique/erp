
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_stage_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sequence');

            $table->boolean('is_quality_gate')->default(false);
            $table->boolean('requires_equipment')->default(false);
            $table->boolean('requires_quantity_confirmation')->default(true);
            $table->boolean('is_active')->default(true);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(
                ['sequence', 'name'],
                'production_stage_sequence_name_unique'
            );

            $table->index(['is_active', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_stage_definitions');
    }
};