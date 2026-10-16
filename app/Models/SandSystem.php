<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A sand system the foundry moulds with. This list feeds every sand picker. Patterns, stations, work lists and moulding runs
 * record the sand by its name, so a rename is carried through to them.
 */
class SandSystem extends Model
{
    use HasUuids, SoftDeletes;

    public const TYPES = [
        'MOLASSES_BONDED' => 'Molasses-bonded',
        'CLAY_BONDED' => 'Clay-bonded (green sand)',
        'CHEMICAL_BONDED' => 'Chemically bonded',
        'OTHER' => 'Other',
    ];

    protected $guarded = [];

    protected $casts = ['sort_order' => 'integer'];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** @return array<int, string> the active sands, in the order set on the list */
    public static function names(): array
    {
        return static::ordered()->where('status', 'ACTIVE')->pluck('name')->all();
    }

    /** @return array<int, string> every sand on the list, inactive ones included: the values a record may hold */
    public static function allNames(): array
    {
        return static::ordered()->pluck('name')->all();
    }

    /**
     * The active sands to offer in a picker, plus the values a record already holds so editing it does not lose them.
     *
     * @return array<int, string>
     */
    public static function choices(?string ...$current): array
    {
        return array_values(array_unique(array_merge(static::names(), array_filter($current, fn ($v) => filled($v)))));
    }

    /** Renames the sand and every record that holds the old name. */
    public function rename(string $name): void
    {
        $old = $this->name;
        if ($old === $name) {
            return;
        }
        DB::transaction(function () use ($old, $name) {
            $this->update(['name' => $name]);
            DB::table('patterns')->where('sand_system', $old)->update(['sand_system' => $name]);
            DB::table('moulding_stations')->where('default_sand_system', $old)->update(['default_sand_system' => $name]);
            DB::table('moulding_work_list_items')->where('sand_system', $old)->update(['sand_system' => $name]);
            DB::table('moulding_runs')->where('sand_system', $old)->update(['sand_system' => $name]);
            foreach (DB::table('patterns')->whereJsonContains('compatible_sands', $old)->get(['id', 'compatible_sands']) as $p) {
                $sands = array_map(fn ($s) => $s === $old ? $name : $s, json_decode($p->compatible_sands, true));
                DB::table('patterns')->where('id', $p->id)->update(['compatible_sands' => json_encode(array_values(array_unique($sands)))]);
            }
        });
    }

    /** @return array<string, int> how many records of each kind use this sand */
    public function usage(): array
    {
        return array_filter([
            'patterns' => DB::table('patterns')->whereNull('deleted_at')->where(fn ($q) => $q->where('sand_system', $this->name)->orWhereJsonContains('compatible_sands', $this->name))->count(),
            'stations' => DB::table('moulding_stations')->where('default_sand_system', $this->name)->count(),
            'work list items' => DB::table('moulding_work_list_items')->where('sand_system', $this->name)->count(),
            'moulding runs' => DB::table('moulding_runs')->where('sand_system', $this->name)->count(),
            'sand recipes' => DB::table('sand_recipes')->where('sand_system_id', $this->id)->count(),
            'manufacturing methods' => DB::table('manufacturing_method_sand_options')->where('sand_system_id', $this->id)->count(),
            'sand tests' => DB::table('measurement_sets')->where('sand_system_id', $this->id)->count(),
        ]);
    }
}
