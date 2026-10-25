<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('core_definition_id')->constrained('core_definitions')->restrictOnDelete();
            $table->foreignUuid('product_revision_id')->constrained('product_revisions')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity_per_casting')->default(1);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['core_definition_id', 'product_revision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_requirements');
    }
};
