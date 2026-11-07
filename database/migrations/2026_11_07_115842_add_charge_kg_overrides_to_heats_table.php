<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The charge recipe is shared by every heat that uses it. A furnace operator may change one charge's kilograms for
 * this heat only, so the changes are kept on the heat and never written back to the recipe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heats', function (Blueprint $table) {
            $table->json('charge_kg_overrides')->nullable()->after('charge_rounds');
        });
    }

    public function down(): void
    {
        Schema::table('heats', function (Blueprint $table) {
            $table->dropColumn('charge_kg_overrides');
        });
    }
};
