<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Shop-floor names: gate and riser removal is fettling, and fettling and grinding is grinding. A step the foundry has already
 * renamed keeps its own name. The route's copy of the name follows.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> code => [old name, new name], in an order that never repeats a name */
    private const NAMES = [
        'FETTLING' => ['Fettling and grinding', 'Grinding'],
        'CUTTING' => ['Gate and riser removal', 'Fettling'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $code => [$old, $new]) {
            $this->rename($code, $old, $new);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::NAMES) as $code => [$old, $new]) {
            $this->rename($code, $new, $old);
        }
    }

    private function rename(string $code, string $from, string $to): void
    {
        $id = DB::table('production_stage_definitions')->where('code', $code)->where('name', $from)->value('id');
        if (! $id || DB::table('production_stage_definitions')->whereRaw('lower(name) = ?', [mb_strtolower($to)])->exists()) {
            return;
        }
        DB::table('production_stage_definitions')->where('id', $id)->update(['name' => $to, 'updated_at' => now()]);
        DB::table('operations')->where('stage_definition_id', $id)->update(['name' => $to, 'updated_at' => now()]);
    }
};
