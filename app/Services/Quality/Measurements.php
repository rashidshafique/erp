<?php

namespace App\Services\Quality;

use App\Models\CharacteristicLimit;
use App\Models\Heat;
use App\Models\Measurement;
use App\Models\MeasurementSet;
use App\Models\SandSystem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Measured test data as data: characteristics and their limits are rows, so a new test needs no code.
 * Limits are copied onto each measurement, so a record stays correct if the specification changes later.
 */
class Measurements
{
    public const KINDS = ['MECHANICAL' => 'Mechanical tests', 'MICROSTRUCTURE' => 'Microstructure', 'SAND' => 'Sand tests', 'MOULD' => 'Mould tests'];

    /** Default characteristics offered on the entry form: key => [name, unit, numeric?] */
    public const CATALOG = [
        'MECHANICAL' => [
            'tensile_mpa' => ['Tensile strength Rm', 'MPa', true], 'yield_mpa' => ['Yield strength Rp0.2', 'MPa', true], 'elongation_pct' => ['Elongation A', '%', true],
            'hardness_hb' => ['Hardness', 'HB', true], 'impact_j' => ['Impact energy', 'J', true],
        ],
        'MICROSTRUCTURE' => [
            'nodularity_pct' => ['Nodularity', '%', true], 'nodule_count_mm2' => ['Nodule count', '/mm²', true], 'pearlite_pct' => ['Pearlite', '%', true],
            'ferrite_pct' => ['Ferrite', '%', true], 'carbides_pct' => ['Carbides', '%', true], 'graphite_form' => ['Graphite form (e.g. V/VI)', null, false],
        ],
        'SAND' => [
            'moisture_pct' => ['Moisture', '%', true], 'compactability_pct' => ['Compactability', '%', true], 'green_strength_kpa' => ['Green compression strength', 'kPa', true],
            'permeability' => ['Permeability', null, true], 'active_clay_pct' => ['Active clay', '%', true], 'loi_pct' => ['Loss on ignition', '%', true],
            'molasses_pct' => ['Molasses (sheera) content', '%', true], 'dry_strength_kpa' => ['Dry strength', 'kPa', true], 'sand_temperature_c' => ['Sand temperature', '°C', true],
        ],
        'MOULD' => [
            'hardness_b_min' => ['Mould hardness, lowest spot (B scale)', 'B', true], 'hardness_b_avg' => ['Mould hardness, average (B scale)', 'B', true],
            'cope_spot_1' => ['Cope spot 1', 'B', true], 'cope_spot_2' => ['Cope spot 2', 'B', true], 'cope_spot_3' => ['Cope spot 3', 'B', true], 'cope_spot_4' => ['Cope spot 4', 'B', true], 'cope_spot_5' => ['Cope spot 5', 'B', true],
            'drag_spot_1' => ['Drag spot 1', 'B', true], 'drag_spot_2' => ['Drag spot 2', 'B', true], 'drag_spot_3' => ['Drag spot 3', 'B', true], 'drag_spot_4' => ['Drag spot 4', 'B', true], 'drag_spot_5' => ['Drag spot 5', 'B', true],
        ],
    ];

    public const SAMPLE_TYPES = ['SEPARATELY_CAST' => 'Separately cast (Y-block / keel block)', 'CAST_ON' => 'Cast-on lug', 'FROM_CASTING' => 'Cut from casting'];

    /** Rows for an entry form: the subject's own limits first, then catalog items it does not specify. */
    public function formRows(string $kind, string $subjectType, ?string $subjectId): Collection
    {
        $limits = $subjectId ? CharacteristicLimit::where('subject_type', $subjectType)->where('subject_id', $subjectId)->where('test_group', $kind)->orderBy('sort_order')->get() : collect();
        $rows = $limits->map(fn ($l) => ['key' => $l->characteristic_key, 'name' => $l->name, 'unit' => $l->unit, 'min' => $l->min_value, 'max' => $l->max_value,
            'required' => $l->is_required_for_release, 'numeric' => ($this->numeric($kind, $l->characteristic_key))]);
        foreach (self::CATALOG[$kind] ?? [] as $k => [$n, $u, $num]) {
            if (! $rows->contains('key', $k)) {
                $rows->push(['key' => $k, 'name' => $n, 'unit' => $u, 'min' => null, 'max' => null, 'required' => false, 'numeric' => $num]);
            }
        }

        return $rows->values();
    }

    private function numeric(string $kind, string $key): bool
    {
        return self::CATALOG[$kind][$key][2] ?? true;
    }

    /** @param array<string, string|null> $values characteristic key => value as typed */
    public function record(string $kind, array $context, array $values, string $subjectType, ?string $subjectId): MeasurementSet
    {
        $rows = $this->formRows($kind, $subjectType, $subjectId)->keyBy('key');
        $entered = collect($values)->filter(fn ($v) => $v !== null && trim((string) $v) !== '');
        if ($entered->isEmpty()) {
            throw new InvalidArgumentException('Enter at least one result.');
        }

        return DB::transaction(function () use ($kind, $context, $entered, $rows) {
            $set = MeasurementSet::create($context + ['kind' => $kind, 'taken_at' => $context['taken_at'] ?? now(), 'result' => 'NOT_JUDGED']);
            $judged = 0;
            $failed = [];
            $i = 0;
            foreach ($entered as $key => $raw) {
                $row = $rows[$key] ?? null;
                if (! $row) {
                    throw new InvalidArgumentException("Unknown characteristic {$key}.");
                }
                $i += 10;
                $m = ['measurement_set_id' => $set->id, 'characteristic_key' => $key, 'name' => $row['name'], 'unit' => $row['unit'], 'min_value' => $row['min'], 'max_value' => $row['max'], 'sort_order' => $i, 'passed' => null];
                if ($row['numeric']) {
                    if (! is_numeric($raw)) {
                        throw new InvalidArgumentException("{$row['name']} must be a number.");
                    }
                    $v = (float) $raw;
                    $m['value'] = $v;
                    if ($row['min'] !== null || $row['max'] !== null) {
                        $judged++;
                        $ok = ($row['min'] === null || $v >= $row['min']) && ($row['max'] === null || $v <= $row['max']);
                        $m['passed'] = $ok;
                        if (! $ok) {
                            $failed[] = $row['name'].' '.\App\Support\Formats::number($v, 2).' is '.($row['min'] !== null && $v < $row['min'] ? 'below the minimum '.$row['min'] : 'above the maximum '.$row['max']).($row['unit'] ? ' '.$row['unit'] : '');
                        }
                    }
                } else {
                    $m['text_value'] = mb_substr(trim((string) $raw), 0, 200);
                }
                Measurement::create($m);
            }
            $set->update(['result' => $failed ? 'FAIL' : ($judged > 0 ? 'PASS' : 'NOT_JUDGED'), 'findings' => $failed]);

            return $set->fresh('measurements');
        });
    }

    /** What the grade requires before release that this heat has not yet shown. @return array<int,string> */
    public function missingForRelease(Heat $heat): array
    {
        if (! $heat->iron_grade_id) {
            return [];
        }
        $required = CharacteristicLimit::where('subject_type', 'IRON_GRADE')->where('subject_id', $heat->iron_grade_id)->where('is_required_for_release', true)->get();
        $missing = [];
        foreach ($required as $r) {
            $latest = Measurement::where('characteristic_key', $r->characteristic_key)->whereNotNull('value')
                ->whereIn('measurement_set_id', MeasurementSet::where('heat_id', $heat->id)->where('kind', $r->test_group)->select('id'))
                ->join('measurement_sets', 'measurement_sets.id', '=', 'measurements.measurement_set_id')->orderByDesc('measurement_sets.taken_at')->orderByDesc('measurement_sets.id')->select('measurements.*')->first();
            $v = $latest?->value;
            if ($latest === null) {
                $missing[] = "{$r->name}: no result recorded for heat {$heat->heat_number}.";
            } elseif (($r->min_value !== null && $v < $r->min_value) || ($r->max_value !== null && $v > $r->max_value)) {
                $missing[] = "{$r->name}: latest result {$v} is outside the limits.";
            }
        }

        return $missing;
    }

    /**
     * State of the sand a run used: the latest sand test for that system in the 24 hours up to the run.
     *
     * @return array{state:string, text:string, set:?MeasurementSet}
     */
    public function sandStatus(?string $systemName, CarbonInterface $at): array
    {
        $sys = $systemName ? SandSystem::where('name', 'ilike', $systemName)->first() : null;
        if (! $sys) {
            return ['state' => 'NONE', 'text' => 'No sand system on record for this run.', 'set' => null];
        }
        $set = MeasurementSet::where('kind', 'SAND')->where('sand_system_id', $sys->id)->where('taken_at', '<=', $at)->where('taken_at', '>=', $at->copy()->subDay())
            ->orderByDesc('taken_at')->orderByDesc('id')->first();
        if (! $set) {
            return ['state' => 'NONE', 'text' => "No {$sys->name} sand test in the 24 hours before this run.", 'set' => null];
        }
        if ($set->result === 'FAIL') {
            return ['state' => 'FAIL', 'text' => "Latest {$sys->name} sand test ({$set->taken_at->displayShortDateTime()}) FAILED: ".implode('; ', $set->findings ?? []), 'set' => $set];
        }

        return ['state' => 'OK', 'text' => "Latest {$sys->name} sand test ({$set->taken_at->displayShortDateTime()}): {$set->result}.", 'set' => $set];
    }
}
