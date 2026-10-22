<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A floor entry names the pattern the moulds were made on and the station that made them. Older entries have neither. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_floor_entries', function (Blueprint $table) {
            $table->foreignUuid('pattern_id')->nullable()->after('production_order_id')->constrained('patterns')->nullOnDelete();
            $table->foreignUuid('moulding_station_id')->nullable()->after('pattern_id')->constrained('moulding_stations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quick_floor_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moulding_station_id');
            $table->dropConstrainedForeignId('pattern_id');
        });
    }
};
