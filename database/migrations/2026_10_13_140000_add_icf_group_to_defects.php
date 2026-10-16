<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** ICF (International Committee of Foundry Technical Associations) casting-defect group letters. */
    public const GROUPS = [
        'A' => 'Metallic projections', 'B' => 'Cavities', 'C' => 'Discontinuities', 'D' => 'Defective surface',
        'E' => 'Incomplete casting', 'F' => 'Incorrect dimensions or shape', 'G' => 'Inclusions or structural anomalies',
    ];

    public function up(): void
    {
        Schema::table('quality_defects', fn (Blueprint $t) => $t->string('icf_group', 1)->nullable());
        DB::statement("ALTER TABLE quality_defects ADD CONSTRAINT quality_defect_icf_chk CHECK (icf_group IS NULL OR icf_group IN ('A','B','C','D','E','F','G'))");
        $map = [
            'A' => ['SCAB', 'EROSION', 'MOULD-CRUSH'], 'B' => ['BLOWHOLE', 'PINHOLE', 'SHRINK-CAV', 'MICRO-SHRINK', 'SINK'],
            'C' => ['COLD-SHUT', 'HOT-TEAR', 'COLD-CRACK'], 'D' => ['GRIND-DAMAGE', 'SURFACE-ROUGH', 'COATING-DEFECT'], 'E' => ['MISRUN'],
            'F' => ['CORE-SHIFT', 'MOULD-SHIFT', 'SWELL', 'DIM-OOT'], 'G' => ['SAND-INCL', 'SLAG-DROSS', 'CHILL-CARBIDE', 'LOW-NODULARITY', 'MG-FADE', 'HARDNESS'],
        ];
        foreach ($map as $g => $codes) {
            DB::table('quality_defects')->whereIn('code', $codes)->update(['icf_group' => $g]);
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE quality_defects DROP CONSTRAINT IF EXISTS quality_defect_icf_chk');
        Schema::table('quality_defects', fn (Blueprint $t) => $t->dropColumn('icf_group'));
    }
};
