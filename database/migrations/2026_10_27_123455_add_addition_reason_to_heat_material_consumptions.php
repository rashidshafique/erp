<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Why a material was added outside the recipe's charges (a top-up, a slag or chemistry fix). Optional. */
    public function up(): void
    {
        Schema::table('heat_material_consumptions', function (Blueprint $table) {
            $table->string('addition_reason', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('heat_material_consumptions', function (Blueprint $table) {
            $table->dropColumn('addition_reason');
        });
    }
};
