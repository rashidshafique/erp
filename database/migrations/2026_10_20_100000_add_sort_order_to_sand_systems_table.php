<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The sand systems table becomes the editable list behind every sand picker. Sands that were only in the old fixed list,
 * or that records already use, are added so nothing already recorded falls outside the list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sand_systems', fn (Blueprint $t) => $t->unsignedInteger('sort_order')->default(0));

        $order = ['Sheera', 'Greensand', 'Alphaset', 'Resin-coated sand', 'Other'];
        $used = collect()
            ->merge(DB::table('patterns')->whereNotNull('sand_system')->pluck('sand_system'))
            ->merge(DB::table('patterns')->whereNotNull('compatible_sands')->pluck('compatible_sands')->flatMap(fn ($j) => json_decode($j, true) ?: []))
            ->merge(DB::table('moulding_stations')->whereNotNull('default_sand_system')->pluck('default_sand_system'))
            ->merge(DB::table('moulding_work_list_items')->whereNotNull('sand_system')->pluck('sand_system'))
            ->merge(DB::table('moulding_runs')->whereNotNull('sand_system')->pluck('sand_system'))
            ->map(fn ($n) => trim((string) $n))->filter()->unique();

        $existing = DB::table('sand_systems')->whereNull('deleted_at')->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        $codes = DB::table('sand_systems')->pluck('code')->all();
        foreach (collect($order)->merge($used)->unique(fn ($n) => mb_strtolower($n)) as $name) {
            if (in_array(mb_strtolower($name), $existing, true)) {
                continue;
            }
            $code = $base = Str::upper(Str::limit(Str::slug($name, '_'), 30, '')) ?: 'SAND';
            for ($i = 2; in_array($code, $codes, true); $i++) {
                $code = $base.'_'.$i;
            }
            $codes[] = $code;
            DB::table('sand_systems')->insert(['id' => (string) Str::uuid(), 'code' => $code, 'name' => $name, 'system_type' => 'OTHER', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        }

        $names = DB::table('sand_systems')->orderBy('name')->pluck('name', 'id');
        $position = fn ($name) => ($i = array_search($name, $order, true)) === false ? count($order) : $i;
        foreach ($names->sortBy(fn ($name) => [$position($name), $name])->keys()->values() as $i => $id) {
            DB::table('sand_systems')->where('id', $id)->update(['sort_order' => ($i + 1) * 10]);
        }
    }

    public function down(): void
    {
        Schema::table('sand_systems', fn (Blueprint $t) => $t->dropColumn('sort_order'));
    }
};
