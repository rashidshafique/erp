<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moulding_stations', function (Blueprint $t) {
            $t->string('default_sand_system', 100)->nullable();   // the sand system this moulding line normally runs
        });
    }

    public function down(): void
    {
        Schema::table('moulding_stations', fn (Blueprint $t) => $t->dropColumn('default_sand_system'));
    }
};
