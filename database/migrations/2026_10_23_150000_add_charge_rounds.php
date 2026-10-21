<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A cupola charges its recipe round after round (metal, coke, flux ... again), after a one-off start-up (bed) charge.
        Schema::table('charge_batch_items', function (Blueprint $table) {
            $table->string('charge_stage', 10)->default('ROUND');   // BED: charged once at start-up; ROUND: repeated every charge
        });
        Schema::table('heats', function (Blueprint $table) {
            $table->unsignedSmallInteger('charge_rounds')->nullable();
        });
        Schema::table('heat_material_consumptions', function (Blueprint $table) {
            $table->unsignedSmallInteger('charge_round')->nullable();   // 0 = bed charge
        });
    }

    public function down(): void
    {
        Schema::table('heat_material_consumptions', fn (Blueprint $table) => $table->dropColumn('charge_round'));
        Schema::table('heats', fn (Blueprint $table) => $table->dropColumn('charge_rounds'));
        Schema::table('charge_batch_items', fn (Blueprint $table) => $table->dropColumn('charge_stage'));
    }
};
