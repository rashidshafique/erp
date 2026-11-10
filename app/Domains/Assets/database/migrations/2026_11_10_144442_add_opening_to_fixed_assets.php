<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The day the depreciation and fills brought in with an asset run to; runs charge it only from the day after. */
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->date('opening_to')->nullable()->after('opening_fills');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn('opening_to');
        });
    }
};
