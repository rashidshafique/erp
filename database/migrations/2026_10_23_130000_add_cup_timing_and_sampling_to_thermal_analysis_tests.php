<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // taken_at stays the time the sample was poured into the cup; created_at is when it was entered in the system.
        Schema::table('thermal_analysis_tests', function (Blueprint $table) {
            $table->timestamp('test_started_at')->nullable();
            $table->timestamp('test_finished_at')->nullable();
            $table->string('sample_point', 20)->nullable();
            $table->string('cup_batch', 60)->nullable();
            $table->string('entered_by_name', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('thermal_analysis_tests', fn (Blueprint $table) => $table->dropColumn(['test_started_at', 'test_finished_at', 'sample_point', 'cup_batch', 'entered_by_name']));
    }
};
