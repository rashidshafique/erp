<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thermal_analysis_tests', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->string('voided_by_name', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('thermal_analysis_tests', fn (Blueprint $table) => $table->dropColumn(['voided_at', 'void_reason', 'voided_by_name']));
    }
};
