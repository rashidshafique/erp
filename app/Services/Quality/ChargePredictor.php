<?php

namespace App\Services\Quality;

use App\Models\AppSetting;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\Material;
use Illuminate\Support\Collection;

/**
 * Works out what a charge will melt to from the chemistry attached to its materials, and sets that against the iron grade's
 * windows. Chemistry on a material is optional: a charge is only judged when enough of its metal weight has chemistry
 * (setting `charge_prediction_min_coverage`, 90 % unless changed).
 *
 * For each element the prediction is the sum, over the charge, of weight x percentage x recovery, divided by the weight of
 * metal that has chemistry (fuel, flux, sand and binder are not metal, and a material without chemistry is left out rather than
 * counted as nothing). The spread from each material's minimum and maximum gives a low and a high figure beside the typical one.
 *
 * This is advice only. It gates nothing: no heat, charge or pour is held or refused because of it.
 *
 * Verdicts: DEVIATES when a typical figure is outside the grade, AT_RISK when it is inside but the spread crosses a limit,
 * CONFORMS when the whole spread is inside, PARTIAL when it is inside on the elements that have data but a limited element has none, INSUFFICIENT when too little of the charge has chemistry, NO_GRADE without a grade.
 */
class ChargePredictor
{
    public const DEFAULT_MIN_COVERAGE = 90;

    /**
     * @param  array<string, float>  $kgByMaterial  Material id => kilograms charged.
     * @return array{verdict: string, total_kg: float, metal_kg: float, covered_kg: float, coverage: float, enough_data: bool, elements: array<string, array<string, mixed>>, ce: ?array<string, mixed>, notes: list<string>}
     */
    public function predict(array $kgByMaterial, ?IronGrade $grade): array
    {
        $kgByMaterial = array_filter($kgByMaterial, fn ($kg) => $kg > 0);
        $materials = Material::with('elements')->whereIn('id', array_keys($kgByMaterial))->get()->keyBy('id');

        $metalKg = 0.0;
        $coveredKg = 0.0;
        $unknown = [];
        foreach ($kgByMaterial as $id => $kg) {
            $material = $materials[$id] ?? null;
            if (! $material || in_array($material->material_type, Material::NON_METAL_TYPES, true)) {
                continue;
            }
            $metalKg += $kg;
            if ($material->elements->isNotEmpty()) {
                $coveredKg += $kg;
            } else {
                $unknown[] = $material->code;
            }
        }
        $coverage = $metalKg > 0 ? $coveredKg / $metalKg : 0.0;
        $enough = $metalKg > 0 && $coverage * 100 >= $this->minimumCoverage();

        $elements = [];
        foreach (IronGrade::ELEMENTS as $key => $label) {
            $sum = ['nominal' => 0.0, 'low' => 0.0, 'high' => 0.0];
            $present = false;
            foreach ($kgByMaterial as $id => $kg) {
                $row = ($materials[$id] ?? null)?->elements->firstWhere('element', $key);
                if (! $row) {
                    continue;
                }
                $present = true;
                $factor = $kg * (float) $row->recovery_pct / 100 / 100;
                $nominal = (float) $row->nominal_pct;
                $sum['nominal'] += $factor * $nominal;
                $sum['low'] += $factor * ($row->min_pct !== null ? (float) $row->min_pct : $nominal);
                $sum['high'] += $factor * ($row->max_pct !== null ? (float) $row->max_pct : $nominal);
            }
            $values = $present && $coveredKg > 0 ? array_map(fn ($v) => round($v / $coveredKg * 100, 4), $sum) : ['nominal' => null, 'low' => null, 'high' => null];
            $elements[$key] = ['label' => $label] + $values + $this->windowFor($grade, $key, $values['nominal'], $values['low'], $values['high']);
        }

        $ce = $this->carbonEquivalent($elements, $grade);
        $verdict = $this->verdict($grade, $enough, $elements, $ce);
        $notes = [];
        if ($unknown !== []) {
            $notes[] = 'No chemistry recorded for '.implode(', ', array_unique($unknown)).'.';
        }
        if ($metalKg > 0 && ! $enough) {
            $notes[] = 'Only '.round($coverage * 100).' % of the metal charged has chemistry; '.$this->minimumCoverage().' % is needed to judge it.';
        }

        return ['verdict' => $verdict, 'total_kg' => round(array_sum($kgByMaterial), 3), 'metal_kg' => round($metalKg, 3), 'covered_kg' => round($coveredKg, 3), 'coverage' => round($coverage, 4),
            'enough_data' => $enough, 'elements' => $elements, 'ce' => $ce, 'notes' => $notes];
    }

    /**
     * The prediction for a heat: from the recipe's plan, from what has been charged so far, and set beside the latest measured reading.
     *
     * @param  array<string, float>  $plannedKg  Material id => planned kilograms.
     * @return array{plan: ?array<string, mixed>, charged: ?array<string, mixed>, measured: ?array<string, ?float>, measured_label: ?string, deviations: array<string, string>}
     */
    public function forHeat(Heat $heat, array $plannedKg): array
    {
        $grade = $heat->ironGrade;
        $chargedKg = $heat->materialConsumptions->whereNotNull('material_id')->groupBy('material_id')->map(fn (Collection $rows) => (float) $rows->sum('actual_qty_kg'))->all();

        $plan = array_sum($plannedKg) > 0 ? $this->predict($plannedKg, $grade) : null;
        $charged = array_sum($chargedKg) > 0 ? $this->predict($chargedKg, $grade) : null;

        $reading = $heat->chemistryReadings->last();
        $measured = null;
        $deviations = [];
        if ($reading) {
            $measured = [];
            foreach (ChemistryJudge::COLUMNS as $key => $column) {
                $measured[$key] = $reading->{$column} !== null ? (float) $reading->{$column} : null;
            }
            $basis = $charged ?? $plan;
            foreach ($basis['elements'] ?? [] as $key => $element) {
                if ($measured[$key] === null || $element['nominal'] === null || ! $basis['enough_data']) {
                    continue;
                }
                $slack = 0.0005;
                if ($measured[$key] < $element['low'] - $slack) {
                    $deviations[$key] = 'below';
                } elseif ($measured[$key] > $element['high'] + $slack) {
                    $deviations[$key] = 'above';
                }
            }
        }

        return ['plan' => $plan, 'charged' => $charged, 'measured' => $measured, 'measured_label' => $reading ? '#'.$reading->reading_number.($reading->sample_stage ? ' · '.$reading->sample_stage : '') : null, 'deviations' => $deviations];
    }

    /** @return array{grade_min: ?float, grade_max: ?float, status: string, gap: ?float} */
    private function windowFor(?IronGrade $grade, string $key, ?float $nominal, ?float $low, ?float $high): array
    {
        $min = $grade?->{$key.'_min'} !== null ? (float) $grade->{$key.'_min'} : null;
        $max = $grade?->{$key.'_max'} !== null ? (float) $grade->{$key.'_max'} : null;

        return ['grade_min' => $min, 'grade_max' => $max] + $this->judge($min, $max, $nominal, $low, $high);
    }

    /** @return array{status: string, gap: ?float} */
    private function judge(?float $min, ?float $max, ?float $nominal, ?float $low, ?float $high): array
    {
        if ($min === null && $max === null) {
            return ['status' => 'NOT_LIMITED', 'gap' => null];
        }
        if ($nominal === null) {
            return ['status' => 'NO_DATA', 'gap' => null];
        }
        if ($min !== null && $nominal < $min) {
            return ['status' => 'OUT', 'gap' => round($nominal - $min, 4)];
        }
        if ($max !== null && $nominal > $max) {
            return ['status' => 'OUT', 'gap' => round($nominal - $max, 4)];
        }
        if (($min !== null && $low < $min) || ($max !== null && $high > $max)) {
            return ['status' => 'AT_RISK', 'gap' => null];
        }

        return ['status' => 'OK', 'gap' => null];
    }

    /** Carbon equivalent C + (Si + P) / 3 for the typical, low and high figures, judged against the grade's window. @return ?array<string, mixed> */
    private function carbonEquivalent(array $elements, ?IronGrade $grade): ?array
    {
        if ($elements['c']['nominal'] === null || $elements['si']['nominal'] === null) {
            return null;
        }
        $figure = fn (string $which) => round($elements['c'][$which] + ($elements['si'][$which] + ($elements['p'][$which] ?? 0)) / 3, 3);
        [$nominal, $low, $high] = [$figure('nominal'), $figure('low'), $figure('high')];
        $min = $grade?->ce_min !== null ? (float) $grade->ce_min : null;
        $max = $grade?->ce_max !== null ? (float) $grade->ce_max : null;

        return ['label' => 'CE', 'nominal' => $nominal, 'low' => $low, 'high' => $high, 'grade_min' => $min, 'grade_max' => $max] + $this->judge($min, $max, $nominal, $low, $high);
    }

    /** @param  array<string, array<string, mixed>>  $elements */
    private function verdict(?IronGrade $grade, bool $enough, array $elements, ?array $ce): string
    {
        if (! $grade) {
            return 'NO_GRADE';
        }
        if (! $enough) {
            return 'INSUFFICIENT';
        }
        $statuses = array_column([...array_values($elements), ...($ce ? [$ce] : [])], 'status');

        if (in_array('OUT', $statuses, true)) {
            return 'DEVIATES';
        }
        if (in_array('AT_RISK', $statuses, true)) {
            return 'AT_RISK';
        }

        return in_array('NO_DATA', $statuses, true) ? 'PARTIAL' : 'CONFORMS';
    }

    private function minimumCoverage(): float
    {
        return (float) AppSetting::getValue('charge_prediction_min_coverage', (string) self::DEFAULT_MIN_COVERAGE);
    }
}
