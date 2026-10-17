<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sheera moulding here uses natural river sand from the Jhelum river rather than graded silica sand. River sand is added
 * to the list and becomes Sheera's main sand, unless someone has already chosen Sheera's sands on the settings page.
 */
return new class extends Migration
{
    public function up(): void
    {
        $riverSand = DB::table('base_sands')->whereNull('deleted_at')->whereRaw('lower(name) = ?', ['river sand'])->value('id');
        if (! $riverSand) {
            $riverSand = (string) Str::uuid();
            DB::table('base_sands')->insert([
                'id' => $riverSand, 'code' => 'RIVER', 'name' => 'River sand',
                'description' => 'Natural river sand from the Jhelum river, used for sheera (molasses-bonded) moulding.',
                'status' => 'ACTIVE', 'sort_order' => 15, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $sheera = DB::table('sand_systems')->whereNull('deleted_at')->whereRaw('lower(name) = ?', ['sheera'])->value('id');
        $silica = DB::table('base_sands')->where('code', 'SILICA')->value('id');
        if (! $sheera) {
            return;
        }
        $links = DB::table('sand_system_sands')->where('sand_system_id', $sheera)->get();
        $stillDefault = $links->isEmpty() || ($links->count() === 1 && $links[0]->base_sand_id === $silica && $links[0]->is_main);
        if ($stillDefault) {
            DB::table('sand_system_sands')->where('sand_system_id', $sheera)->delete();
            DB::table('sand_system_sands')->insert([
                'sand_system_id' => $sheera, 'base_sand_id' => $riverSand, 'is_main' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {}
};
