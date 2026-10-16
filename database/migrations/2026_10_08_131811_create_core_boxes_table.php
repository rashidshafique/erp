<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_boxes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('core_definition_id')
                ->constrained('core_definitions')
                ->restrictOnDelete();

            $table->string('code', 40)->unique();
            $table->string('name');

            $table->string('revision', 20)->default('A');

            $table->string('status', 20)->default('ACTIVE');

            $table->string('material', 50)->nullable();

            $table->unsignedInteger('cavities')->default(1);

            $table->decimal('nominal_core_weight_kg', 12, 4)->nullable();

            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->date('commissioned_on')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique([
                'core_definition_id',
                'revision'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_boxes');
    }
};