<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mould_cavities', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('mould_layer_id')
                ->constrained('mould_layers')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('cavity_number');

            $table->foreignUuid('pattern_revision_id')
                ->constrained('pattern_revisions')
                ->restrictOnDelete();

            $table->foreignUuid('product_revision_id')
                ->constrained('product_revisions')
                ->restrictOnDelete();

            $table->decimal('nominal_casting_weight_kg', 12, 4)->nullable();
            $table->unsignedSmallInteger('castings_per_cavity')->default(1);

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['mould_layer_id', 'cavity_number'],
                'mould_cavities_layer_cavity_unique'
            );

            $table->index('pattern_revision_id');
            $table->index('product_revision_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mould_cavities');
    }
};