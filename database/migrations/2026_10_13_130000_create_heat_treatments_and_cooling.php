<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heat_treatments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('heat_id')->constrained('heats')->cascadeOnDelete();
            $t->string('treatment_type', 30);
            $t->timestampTz('treated_at');
            $t->string('material', 120)->nullable();       // e.g. FeSiMg 6%, FeSi75 inoculant
            $t->decimal('amount_kg', 10, 3)->nullable();
            $t->decimal('metal_treated_kg', 10, 3)->nullable();
            $t->decimal('addition_pct', 6, 3)->nullable();
            $t->string('method', 30)->nullable();
            $t->decimal('temperature_c', 6, 1)->nullable();
            $t->string('ladle_reference', 60)->nullable();
            $t->decimal('recovery_pct', 5, 1)->nullable();
            $t->string('treated_by', 100)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['heat_id', 'treated_at']);
        });
        DB::statement("ALTER TABLE heat_treatments ADD CONSTRAINT heat_treatment_type_chk CHECK (treatment_type IN ('MG_TREATMENT','INOCULATION_LADLE','INOCULATION_STREAM','INOCULATION_INMOULD','DESULPHURISATION','CARBON_ADJUST','OTHER'))");

        Schema::table('pattern_revisions', fn (Blueprint $t) => $t->smallInteger('min_cooling_minutes')->nullable());
        DB::statement('ALTER TABLE pattern_revisions ADD CONSTRAINT pattern_cooling_chk CHECK (min_cooling_minutes IS NULL OR (min_cooling_minutes >= 0 AND min_cooling_minutes <= 10080))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pattern_revisions DROP CONSTRAINT IF EXISTS pattern_cooling_chk');
        Schema::table('pattern_revisions', fn (Blueprint $t) => $t->dropColumn('min_cooling_minutes'));
        Schema::dropIfExists('heat_treatments');
    }
};
