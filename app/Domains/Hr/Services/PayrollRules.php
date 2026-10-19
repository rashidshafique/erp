<?php

namespace App\Domains\Hr\Services;

use App\Models\AppSetting;

/** The live payroll rules: what was saved on screen, over the placeholder defaults in config/payroll.php. */
class PayrollRules
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $rules = config('payroll.rules');
        try {
            $saved = json_decode((string) AppSetting::getValue('payroll.rules', ''), true);
        } catch (\Throwable) {
            $saved = null;
        }
        if (is_array($saved)) {
            foreach ($saved as $k => $v) {
                if (array_key_exists($k, $rules)) {
                    $rules[$k] = $v;
                }
            }
        }

        return self::$cache = $rules;
    }

    public static function get(string $key, $default = null)
    {
        return self::all()[$key] ?? $default;
    }

    public static function save(array $rules): void
    {
        $keep = array_intersect_key($rules, config('payroll.rules'));
        AppSetting::setValue('payroll.rules', json_encode($keep), 'Payroll rules edited by HR');
        self::flush();
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** Annual income tax on a salary, from the slab table, with the surcharge on very high incomes. */
    public static function annualTax(float $income): float
    {
        if (! self::get('tax_enabled') || $income <= 0) {
            return 0.0;
        }
        $slab = null;
        foreach (self::get('tax_slabs', []) as $s) {
            if ($income >= (float) $s['from'] && ($slab === null || (float) $s['from'] >= (float) $slab['from'])) {
                $slab = $s;
            }
        }
        if (! $slab) {
            return 0.0;
        }
        $tax = (float) $slab['fixed'] + ($income - (float) $slab['from']) * (float) $slab['rate'] / 100;
        $over = (float) self::get('tax_surcharge_over', 0);
        if ($over > 0 && $income > $over) {
            $tax *= 1 + (float) self::get('tax_surcharge_percent', 0) / 100;
        }

        return max(0.0, $tax);
    }

    /** Round a money amount the way the rules say. */
    public static function round(float $v): float
    {
        $unit = max(0.01, (float) self::get('round_to', 1));

        return round(round($v / $unit) * $unit, 2);
    }
}
