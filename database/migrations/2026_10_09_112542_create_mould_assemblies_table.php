<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mould_assemblies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('assembly_number', 80)->unique();

            $table->foreignUuid('mould_configuration_id')
                ->constrained('mould_configurations')
                ->restrictOnDelete();

            $table->string('status', 30)->default('PREPARED');

            $table->timestampTz('assembled_at')->nullable();
            $table->string('assembled_by', 100)->nullable();
            $table->string('checked_by', 100)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'assembled_at']);
            $table->index('mould_configuration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mould_assemblies');
    }
};