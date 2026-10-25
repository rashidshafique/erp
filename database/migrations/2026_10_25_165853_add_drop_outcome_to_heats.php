<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How a cupola campaign ended at the bottom drop: finished (the run reached the share of the expected time that counts as done, or
 * someone confirmed it) or dropped prematurely (with the reason). The expected and actual minutes are kept as judged at the drop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heats', function (Blueprint $table) {
            $table->string('drop_outcome', 12)->nullable();
            $table->text('drop_reason')->nullable();
            $table->unsignedInteger('drop_expected_minutes')->nullable();
            $table->unsignedInteger('drop_actual_minutes')->nullable();
        });
        DB::statement("ALTER TABLE heats ADD CONSTRAINT heats_drop_outcome_valid CHECK (drop_outcome IS NULL OR drop_outcome IN ('FINISHED','PREMATURE'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE heats DROP CONSTRAINT IF EXISTS heats_drop_outcome_valid');
        Schema::table('heats', fn (Blueprint $table) => $table->dropColumn(['drop_outcome', 'drop_reason', 'drop_expected_minutes', 'drop_actual_minutes']));
    }
};
