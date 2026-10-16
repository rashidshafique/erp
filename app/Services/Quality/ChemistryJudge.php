<?php

namespace App\Services\Quality;

use App\Models\IronGrade;

/** Compares a chemistry reading with the iron grade's windows. */
class ChemistryJudge
{
    /** Percent column on the reading for each element key of the grade. */
    public const COLUMNS = [
        'c' => 'carbon_percent', 'si' => 'silicon_percent', 'mn' => 'manganese_percent', 'p' => 'phosphorus_percent',
        's' => 'sulphur_percent', 'cr' => 'chromium_percent', 'cu' => 'copper_percent', 'mo' => 'molybdenum_percent', 'mg' => 'magnesium_percent',
    ];

    /** Carbon equivalent C + (Si + P) / 3. Needs carbon and silicon. */
    public function carbonEquivalent(array $v): ?float
    {
        if (! isset($v['carbon_percent'], $v['silicon_percent']) || $v['carbon_percent'] === '' || $v['silicon_percent'] === '') {
            return null;
        }

        return round((float) $v['carbon_percent'] + ((float) $v['silicon_percent'] + (float) ($v['phosphorus_percent'] ?? 0)) / 3, 3);
    }

    /**
     * @param  array<string, mixed>  $v  reading values keyed by column name
     * @return array{ok: ?bool, findings: array<int, string>}
     */
    public function judge(?IronGrade $grade, array $v, ?float $ce = null): array
    {
        if (! $grade) {
            return ['ok' => null, 'findings' => ['The heat has no iron grade, so the analysis cannot be judged.']];
        }
        $f = [];
        $limited = false;
        foreach (self::COLUMNS as $key => $column) {
            $min = $grade->{$key.'_min'};
            $max = $grade->{$key.'_max'};
            if ($min === null && $max === null) {
                continue;
            }
            $limited = true;
            $val = $v[$column] ?? null;
            $label = IronGrade::ELEMENTS[$key];
            if ($val === null || $val === '') {
                $f[] = "{$label} was not measured but a limit is set.";

                continue;
            }
            $val = (float) $val;
            if ($min !== null && $val < (float) $min) {
                $f[] = "{$label} {$val} % is below the minimum {$min} %.";
            }
            if ($max !== null && $val > (float) $max) {
                $f[] = "{$label} {$val} % is above the maximum {$max} %.";
            }
        }
        if ($grade->ce_min !== null || $grade->ce_max !== null) {
            $limited = true;
            if ($ce === null) {
                $f[] = 'Carbon equivalent could not be worked out (carbon and silicon are needed).';
            } else {
                if ($grade->ce_min !== null && $ce < (float) $grade->ce_min) {
                    $f[] = "Carbon equivalent {$ce} is below the minimum {$grade->ce_min}.";
                }
                if ($grade->ce_max !== null && $ce > (float) $grade->ce_max) {
                    $f[] = "Carbon equivalent {$ce} is above the maximum {$grade->ce_max}.";
                }
            }
        }

        return ['ok' => $limited ? $f === [] : null, 'findings' => $limited ? $f : ['No chemistry limits are set for this grade.']];
    }
}
