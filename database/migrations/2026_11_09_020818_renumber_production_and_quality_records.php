<?php

use App\Support\DocumentNumbers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPES = ['production_order', 'moulding_list', 'moulding_run', 'mould_assembly', 'planned_heat', 'heat', 'pour', 'casting_lot', 'core_lot', 'ncr', 'inspection', 'deviation'];

    /**
     * Production orders W-, moulding lists G-, runs M-, assemblies A-, planned heats F-, heats H-, pours U-, casting lots L-, core
     * lots O-, NCRs N-, inspections X- and deviations V- from 10000 in date order. A casting lot's short code becomes its number
     * too; the old code and number stay findable (number_history), so stamped parts and printed labels still lead to the lot.
     */
    public function up(): void
    {
        $pairs = DocumentNumbers::renumberTypes(self::TYPES);
        if (! Schema::hasTable('casting_lots')) {
            return;
        }
        $now = now();
        $history = [];
        foreach (DB::table('casting_lots')->whereNotNull('code')->whereColumn('code', '<>', 'lot_number')->get(['id', 'code', 'lot_number']) as $lot) {
            $history[] = ['table_name' => 'casting_lots', 'column_name' => 'code', 'record_id' => (string) $lot->id, 'old_number' => $lot->code, 'new_number' => $lot->lot_number, 'created_at' => $now];
            $pairs[$lot->code] = $lot->lot_number;
        }
        DB::table('casting_lots')->whereNotNull('lot_number')->update(['code' => DB::raw('lot_number')]);
        foreach (array_chunk($history, 500) as $chunk) {
            DB::table('number_history')->insert($chunk);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('casting_lots')) {
            foreach (DB::table('number_history')->where('table_name', 'casting_lots')->where('column_name', 'code')->get() as $h) {
                DB::table('casting_lots')->where('id', $h->record_id)->update(['code' => $h->old_number]);
            }
            DB::table('number_history')->where('table_name', 'casting_lots')->where('column_name', 'code')->delete();
        }
        DocumentNumbers::restoreTypes(self::TYPES);
    }
};
