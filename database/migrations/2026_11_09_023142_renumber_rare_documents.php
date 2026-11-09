<?php

use App\Support\DocumentNumbers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPES = ['capa', 'pattern_development', 'maintenance_work_order', 'breakdown', 'tooling_maintenance', 'payroll_period', 'leave_request', 'hr_advance'];

    /**
     * The less used documents take two-letter prefixes from 10000: corrective actions CA-, pattern developments PD-, maintenance
     * work orders MW-, breakdowns MB-, tooling maintenance TM-, payroll periods PP-, leave requests LR-, employee advances EA-.
     * Copies of numbers renumbered earlier (heat numbers on internal-return material lots) follow too.
     */
    public function up(): void
    {
        DocumentNumbers::renumberTypes(self::TYPES);
        $this->heatReferences(DB::table('number_history')->where('table_name', 'heats')->pluck('new_number', 'old_number')->all());
    }

    public function down(): void
    {
        $this->heatReferences(DB::table('number_history')->where('table_name', 'heats')->pluck('old_number', 'new_number')->all());
        DocumentNumbers::restoreTypes(self::TYPES);
    }

    /** Material lots made from a heat's leftover metal name the heat. @param array<string, string> $pairs */
    private function heatReferences(array $pairs): void
    {
        if (! Schema::hasTable('material_lots')) {
            return;
        }
        foreach (array_chunk(array_keys($pairs), 500) as $olds) {
            foreach (DB::table('material_lots')->whereIn('heat_or_batch_reference', $olds)->get(['id', 'heat_or_batch_reference']) as $lot) {
                DB::table('material_lots')->where('id', $lot->id)->update(['heat_or_batch_reference' => $pairs[$lot->heat_or_batch_reference]]);
            }
        }
    }
};
