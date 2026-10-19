<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Mould tests (hardness on the compacted mould), linked to a run and optionally to one of its moulds. */
    public function up(): void
    {
        DB::statement('ALTER TABLE measurement_sets DROP CONSTRAINT meas_set_kind_chk');
        DB::statement("ALTER TABLE measurement_sets ADD CONSTRAINT meas_set_kind_chk CHECK (kind IN ('MECHANICAL','MICROSTRUCTURE','SAND','MOULD'))");
        Schema::table('measurement_sets', function (Blueprint $table) {
            $table->foreignUuid('mould_assembly_id')->nullable()->constrained('mould_assemblies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('measurement_sets')->where('kind', 'MOULD')->delete();
        Schema::table('measurement_sets', fn (Blueprint $table) => $table->dropConstrainedForeignId('mould_assembly_id'));
        DB::statement('ALTER TABLE measurement_sets DROP CONSTRAINT meas_set_kind_chk');
        DB::statement("ALTER TABLE measurement_sets ADD CONSTRAINT meas_set_kind_chk CHECK (kind IN ('MECHANICAL','MICROSTRUCTURE','SAND'))");
    }
};
