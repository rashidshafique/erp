<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mould_layers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('mould_assembly_id')
                ->constrained('mould_assemblies')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('layer_number');
            $table->string('layer_type', 30);
            $table->string('flask_reference', 80)->nullable();

            $table->decimal('flask_length_mm', 10, 2)->nullable();
            $table->decimal('flask_width_mm', 10, 2)->nullable();
            $table->decimal('flask_height_mm', 10, 2)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['mould_assembly_id', 'layer_number'],
                'mould_layers_assembly_layer_unique'
            );

            $table->index('layer_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mould_layers');
    }
};