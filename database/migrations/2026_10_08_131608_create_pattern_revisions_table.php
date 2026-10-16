<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pattern_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('pattern_id')
                ->constrained('patterns')
                ->restrictOnDelete();

            $table->string('revision', 20);

            $table->string('status', 20)->default('DRAFT');

            $table->string('parting_type', 30)->nullable();

            $table->unsignedInteger('cavities_per_mould')->nullable();
            $table->unsignedInteger('layers_per_mould')->nullable();

            $table->decimal('pattern_weight_kg', 12, 4)->nullable();

            $table->text('change_description')->nullable();
            $table->text('engineering_notes')->nullable();

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->timestamps();

            $table->unique([
                'pattern_id',
                'revision'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_revisions');
    }
};