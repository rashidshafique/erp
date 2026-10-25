<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The charges someone removed from a heat on purpose: the schedule leaves them alone, so they are not added back automatically. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heats', fn (Blueprint $table) => $table->jsonb('skipped_charge_rounds')->nullable());
    }

    public function down(): void
    {
        Schema::table('heats', fn (Blueprint $table) => $table->dropColumn('skipped_charge_rounds'));
    }
};
