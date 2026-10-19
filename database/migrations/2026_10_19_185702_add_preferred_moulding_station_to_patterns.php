<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The station a pattern is normally moulded on; a new run picks it, and the pattern's sand follows its default unless set by hand. */
    public function up(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            $table->foreignUuid('preferred_moulding_station_id')->nullable()->constrained('moulding_stations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patterns', fn (Blueprint $table) => $table->dropConstrainedForeignId('preferred_moulding_station_id'));
    }
};
