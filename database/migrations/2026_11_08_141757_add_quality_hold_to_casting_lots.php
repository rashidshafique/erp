<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Why quality put a lot on hold by itself (waiting for rework, full lots only); the lot clears by itself when its NCRs close. */
    public function up(): void
    {
        Schema::table('casting_lots', function (Blueprint $table) {
            $table->string('quality_hold', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('casting_lots', function (Blueprint $table) {
            $table->dropColumn('quality_hold');
        });
    }
};
