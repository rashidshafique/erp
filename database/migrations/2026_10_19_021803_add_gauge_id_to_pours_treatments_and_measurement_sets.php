<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The pyrometer behind a pour or treatment temperature, and the machine behind a mechanical, microstructure or sand test. */
    public function up(): void
    {
        foreach (['pours', 'heat_treatments', 'measurement_sets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignUuid('gauge_id')->nullable()->constrained('gauges')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['measurement_sets', 'heat_treatments', 'pours'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropConstrainedForeignId('gauge_id'));
        }
    }
};
