<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Closing a heat when the furnace is turned off: the metal left over and where it went. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heats', function (Blueprint $table) {
            $table->decimal('leftover_metal_kg', 12, 2)->nullable()->after('closed_at');
            $table->string('leftover_use', 12)->nullable()->after('leftover_metal_kg');
            $table->string('close_note', 500)->nullable()->after('leftover_use');
        });
        DB::statement("ALTER TABLE heats ADD CONSTRAINT heats_leftover_use_valid CHECK (leftover_use IS NULL OR leftover_use IN ('PIGGED','RETURNS','HELD','SCRAPPED'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE heats DROP CONSTRAINT IF EXISTS heats_leftover_use_valid');
        Schema::table('heats', function (Blueprint $table) {
            $table->dropColumn(['leftover_metal_kg', 'leftover_use', 'close_note']);
        });
    }
};
