<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sand_recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('sand_system_id')
                ->constrained('sand_systems')
                ->restrictOnDelete();

            $table->string('code', 40);
            $table->string('name');

            $table->string('revision', 20)->default('A');
            $table->string('status', 20)->default('DRAFT');

            $table->decimal('target_batch_weight_kg', 12, 3)->nullable();

            $table->decimal('moisture_min_percent', 8, 3)->nullable();
            $table->decimal('moisture_max_percent', 8, 3)->nullable();

            $table->decimal('temperature_min_c', 8, 2)->nullable();
            $table->decimal('temperature_max_c', 8, 2)->nullable();

            $table->decimal('mix_time_seconds', 10, 2)->nullable();

            $table->text('instructions')->nullable();
            $table->text('notes')->nullable();

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->timestamps();

            $table->unique([
                'sand_system_id',
                'code',
                'revision'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sand_recipes');
    }
};