<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('sand_systems')->exists()) {
            return;
        }
        // Names match what moulding runs record in "sand system".
        $rows = [
            ['SHEERA', 'Sheera', 'MOLASSES_BONDED', 'Sand bonded with molasses (sheera) and water; hand moulding on match plates and loose patterns.'],
            ['GREENSAND', 'Greensand', 'CLAY_BONDED', 'Bentonite-bonded green sand.'],
            ['ALPHASET', 'Alphaset', 'CHEMICAL_BONDED', 'Chemically bonded (alkaline phenolic) no-bake sand.'],
            ['RESIN_COATED', 'Resin-coated sand', 'CHEMICAL_BONDED', 'Shell / resin-coated sand.'],
        ];
        foreach ($rows as [$code, $name, $type, $desc]) {
            DB::table('sand_systems')->insert(['id' => (string) Str::uuid(), 'code' => $code, 'name' => $name, 'system_type' => $type, 'status' => 'ACTIVE', 'description' => $desc, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
    }
};
