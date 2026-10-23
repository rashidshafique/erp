<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quality notes per step: what to check at the step, kept apart from what is done there. They show where the step is recorded
 * and on the printed lot traveller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_stage_definitions', function (Blueprint $table) {
            $table->text('quality_notes')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('production_stage_definitions', function (Blueprint $table) {
            $table->dropColumn('quality_notes');
        });
    }
};
