<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('manufacturing_method_id')
                ->constrained('manufacturing_methods')
                ->restrictOnDelete();

            $table->string('code', 40);
            $table->string('name');

            $table->string('revision', 20)->default('A');
            $table->string('status', 20)->default('DRAFT');

            $table->boolean('is_primary')->default(false);

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'manufacturing_method_id',
                'code',
                'revision'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_routes');
    }
};