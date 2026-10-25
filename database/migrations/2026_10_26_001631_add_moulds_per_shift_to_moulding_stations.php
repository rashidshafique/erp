<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moulding_stations', function (Blueprint $t) {
            // Good moulds the station makes in one shift, all its patterns together. The planner loads stations with this; empty means not planned.
            $t->unsignedInteger('moulds_per_shift')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('moulding_stations', fn (Blueprint $t) => $t->dropColumn('moulds_per_shift'));
    }
};
