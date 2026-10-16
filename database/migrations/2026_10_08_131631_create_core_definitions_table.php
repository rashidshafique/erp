<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code', 40)->unique();
            $table->string('name');

            $table->string('core_type', 30);

            $table->string('status', 20)->default('ACTIVE');

            $table->decimal('nominal_weight_kg', 12, 4)->nullable();

            $table->string('process_type', 30)->nullable();

            $table->text('description')->nullable();
            $table->text('engineering_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_definitions');
    }
};