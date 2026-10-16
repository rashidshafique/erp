<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('manufacturing_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('product_revision_id')
                ->constrained('product_revisions')
                ->restrictOnDelete();

            $table->string('code', 40);

            $table->string('name');

            $table->string('revision', 20)->default('A');

            $table->string('status', 20)->default('DRAFT');

            $table->string('preferred_melting_process', 30)->nullable();

            $table->text('description')->nullable();

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->timestamps();

            $table->unique([
                'product_revision_id',
                'code',
                'revision',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturing_methods');
    }
};
