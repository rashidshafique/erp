<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_centers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code', 40)->unique();
            $table->string('name');

            $table->string('work_center_type', 30);
            $table->string('department', 40)->nullable();

            $table->string('status', 20)->default('ACTIVE');

            $table->decimal('capacity_hours_per_day', 10, 2)->nullable();
            $table->decimal('efficiency_percent', 6, 2)->nullable();

            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_centers');
    }
};