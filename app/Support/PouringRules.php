<?php

namespace App\Support;

/** How pouring is controlled, set under Settings. Each rule has a default that suits the furnace type. */
class PouringRules
{
    use RuleSet;

    public const GATE_MODES = [
        'OFF' => 'No check: pour whenever the moulds are ready',
        'ADVISORY' => 'Advisory: warn, pour, record what was missing',
        'ENFORCED' => 'Enforced: no pour until checks pass',
    ];

    /** What packing does with the chemistry and thermal analysis checks of a lot's heat. */
    public const PACKING_MODES = [
        'FOLLOW' => 'Same as pouring for the heat\'s furnace',
        'OFF' => 'No check',
        'ADVISORY' => 'Advisory: warn but allow packing',
        'ENFORCED' => 'Enforced: no packing until passed or waived with a reason',
    ];

    /** key => [setting, label, options (value => description) or null for a number, default] */
    public const RULES = [
        'gate_cupola' => ['pouring.gate.cupola', 'Checks before pouring: cupola heats', self::GATE_MODES, 'ADVISORY'],
        'gate_induction' => ['pouring.gate.induction', 'Checks before pouring: induction heats', self::GATE_MODES, 'ENFORCED'],
        'gate_other' => ['pouring.gate.other', 'Checks before pouring: other furnaces', self::GATE_MODES, 'ENFORCED'],
        'gate_packing' => ['pouring.gate.packing', 'Chemistry and thermal checks at packing', self::PACKING_MODES, 'FOLLOW'],
        'first_iron_minutes' => ['pouring.first_iron_minutes', 'Cupola: first-iron minutes after first tap (0 = none)', null, '30'],
        'hold_first_iron' => ['pouring.hold_first_iron', 'Lots made from first iron', [
            'HOLD' => 'Hold them until QC passes',
            'FREE' => 'Treat them like any other lot',
        ], 'HOLD'],
        'lightup_to_first_charge_minutes' => ['charging.lightup_to_first_charge_minutes', 'Cupola: minutes from light-up to first charge', null, '60'],
        'drop_finished_percent' => ['pouring.drop_finished_percent', 'Cupola: % of expected run that counts as finished at drop', null, '90'],
        'test_interval_minutes' => ['pouring.test_interval_minutes', 'Cupola: minutes between samples (reminder)', null, '30'],
        'charge_late_minutes' => ['charging.late_minutes', 'Minutes a charge can be late before melt shop and planner are told', null, '10'],
        'unaccounted_percent' => ['pouring.unaccounted_percent', 'Closing a heat: unaccounted metal that tells the planner, as % of tapped', null, '2'],
        'unaccounted_min_kg' => ['pouring.unaccounted_min_kg', 'Closing a heat: never tell the planner about less unaccounted metal than (kg)', null, '5'],
        'auto_charge' => ['charging.auto', 'Charges that come due on a running heat', [
            'AUTO' => 'Automatic: recorded from the recipe when due',
            'MANUAL' => 'Manual: someone confirms each charge',
        ], 'AUTO'],
        'containment' => ['pouring.containment', 'When a sample comes back out of limits', [
            'HOLD' => 'Hold the lots poured in the affected period',
            'NOTE' => 'Flag the pours only',
        ], 'HOLD'],
        'treatment' => ['enforce_treatment_records', 'Treatment before pouring', [
            'false' => 'Warn: noted on the pour',
            'true' => 'Enforced: treatment and inoculation in time',
        ], 'false'],
        'max_fade_minutes' => ['max_fade_minutes', 'Fade minutes, treatment to pour', null, '15'],
        'auto_post_returns' => ['auto_post_internal_returns_to_stock', 'Internal returns on a heat charge', [
            'false' => 'Costed in the charge only',
            'true' => 'Posted back to stock as traceable receipts',
        ], 'false'],
    ];

    /** @return list<string> */
    protected static function numberRule(string $rule): array
    {
        return match ($rule) {
            'max_fade_minutes' => ['integer', 'min:1', 'max:120'],
            'unaccounted_percent' => ['integer', 'min:0', 'max:100'],
            'unaccounted_min_kg' => ['integer', 'min:0', 'max:100000'],
            default => ['integer', 'min:0', 'max:1440'],
        };
    }

    /** Whether a pour is refused until magnesium treatment and inoculation are recorded within the fade time. */
    public static function enforcesTreatment(): bool
    {
        return self::get('treatment') === 'true';
    }

    /** Whether internal returns on a posted heat charge go back to stock with their own receipts and cost layers. */
    public static function postsReturnsToStock(): bool
    {
        return self::get('auto_post_returns') === 'true';
    }

    /** Whether due charges are recorded by the scheduler without anyone confirming them. */
    public static function autoCharges(): bool
    {
        return self::get('auto_charge') === 'AUTO';
    }

    public static function minutes(string $rule): int
    {
        return (int) self::get($rule);
    }

    /** Whether metal missing at the close of a heat is enough to tell the planner: over the % of tapped, and never under the kg floor. */
    public static function unaccountedMatters(float $unaccountedKg, float $tappedKg): bool
    {
        $limit = max((float) self::get('unaccounted_min_kg'), $tappedKg * (float) self::get('unaccounted_percent') / 100);

        return abs($unaccountedKg) >= $limit;
    }

    /** OFF, ADVISORY or ENFORCED for a furnace type. The old switch FOUNDRY_ENFORCE_THERMAL_GATE=false still turns every check off. */
    public static function gateMode(?string $furnaceType): string
    {
        if (! config('foundry.enforce_thermal_gate')) {
            return 'OFF';
        }

        return self::get(match ($furnaceType) {
            'CUPOLA' => 'gate_cupola',
            'INDUCTION' => 'gate_induction',
            default => 'gate_other',
        });
    }

    /** OFF, ADVISORY or ENFORCED for the chemistry and thermal analysis checks when packing a lot from a heat of this furnace type. */
    public static function packingMode(?string $furnaceType): string
    {
        $mode = self::get('gate_packing');

        return $mode === 'FOLLOW' || ! config('foundry.enforce_thermal_gate') ? self::gateMode($furnaceType) : $mode;
    }
}
