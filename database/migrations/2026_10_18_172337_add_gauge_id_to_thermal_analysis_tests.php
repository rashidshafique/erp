<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thermal_analysis_tests', function (Blueprint $table) {
            $table->foreignUuid('gauge_id')->nullable()->constrained('gauges')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('thermal_analysis_tests', fn (Blueprint $table) => $table->dropConstrainedForeignId('gauge_id'));
    }
};
