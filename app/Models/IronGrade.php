<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IronGrade extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'family',
        'standard_code',
        'min_tensile_mpa',
        'min_yield_mpa',
        'min_elongation_pct',
        'c_min',
        'c_max',
        'si_min',
        'si_max',
        'mn_min',
        'mn_max',
        'p_min',
        'p_max',
        's_min',
        's_max',
        'cr_min',
        'cr_max',
        'cu_min',
        'cu_max',
        'mo_min',
        'mo_max',
        'mg_min',
        'mg_max',
        'ce_min',
        'ce_max',
        'tap_temp_min_c',
        'tap_temp_max_c',
        'pour_temp_min_c',
        'pour_temp_max_c',
        'needs_mg_treatment',
        'needs_inoculation',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return ['needs_mg_treatment' => 'boolean', 'needs_inoculation' => 'boolean'];
    }

    public const ELEMENTS = ['c' => 'C', 'si' => 'Si', 'mn' => 'Mn', 'p' => 'P', 's' => 'S', 'cr' => 'Cr', 'cu' => 'Cu', 'mo' => 'Mo', 'mg' => 'Mg'];

    public function aliases(): HasMany
    {
        return $this->hasMany(IronGradeAlias::class);
    }

    public function thermalWindows(): HasMany
    {
        return $this->hasMany(ThermalWindow::class);
    }

    public static function normalize(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));

        return preg_replace('/^EN/', '', $s);
    }

    /** Resolve a designation from any standard (EN, ASTM, ISO, IS, JIS) to a grade, or null. */
    public static function resolve(?string $designation): ?array
    {
        if (blank($designation)) {
            return null;
        }
        $n = self::normalize($designation);
        $hit = IronGradeAlias::where('normalized', $n)->first();
        if (! $hit) {
            // Longer text such as "ASTM A48 Class 35B" contains the alias; the longest alias wins.
            $hit = IronGradeAlias::query()->with('ironGrade')->get()
                ->filter(fn ($a) => strlen($a->normalized) >= 4 && str_contains($n, $a->normalized))
                ->sortByDesc(fn ($a) => strlen($a->normalized))->first();
        }

        return $hit ? ['grade' => $hit->ironGrade, 'exact' => $hit->is_exact, 'alias' => $hit] : null;
    }

    /** Check chemistry (percent values keyed c, si, ..., ce) against this grade's window. */
    public function chemistryFindings(array $values): array
    {
        $out = [];
        foreach (self::ELEMENTS as $k => $label) {
            $v = $values[$k] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $lo = $this->{$k.'_min'};
            $hi = $this->{$k.'_max'};
            if ($lo !== null && (float) $v < (float) $lo) {
                $out[] = "{$label} {$v} % is below the minimum {$lo} %.";
            }
            if ($hi !== null && (float) $v > (float) $hi) {
                $out[] = "{$label} {$v} % is above the maximum {$hi} %.";
            }
        }
        $ce = $values['ce'] ?? null;
        if ($ce !== null && $ce !== '') {
            if ($this->ce_min !== null && (float) $ce < (float) $this->ce_min) {
                $out[] = "CE {$ce} is below the minimum {$this->ce_min}.";
            }
            if ($this->ce_max !== null && (float) $ce > (float) $this->ce_max) {
                $out[] = "CE {$ce} is above the maximum {$this->ce_max}.";
            }
        }

        return $out;
    }
}
