<?php

namespace App\Support;

/** How pouring is controlled, set under Settings. Each rule has a default that suits the furnace type. */
class PouringRules
{
    use RuleSet;

    public const GATE_MODES = [
        'OFF' => 'No check: pour whenever the moulds are ready',
        'ADVISORY' => 'Advisory: show what is missing, let the pour start, and record that it was missing',
        'ENFORCED' => 'Enforced: the pour cannot start until the checks pass',
    ];

    /** What packing does with the chemistry and thermal analysis checks of a lot's heat. */
    public const PACKING_MODES = [
        'FOLLOW' => 'Same as for pouring: the mode of the furnace type the heat was made in',
        'OFF' => 'No check: pack without chemistry or thermal analysis results',
        'ADVISORY' => 'Advisory: show what is missing or failed, but let the lot be packed',
        'ENFORCED' => 'Enforced: the lot cannot be packed until they pass, unless someone waives them for that lot with a reason',
    ];

    /** key => [setting, label, options (value => description) or null for a number, default] */
    public const RULES = [
        'gate_cupola' => ['pouring.gate.cupola', 'Checks before pouring: cupola heats', self::GATE_MODES, 'ADVISORY'],
        'gate_induction' => ['pouring.gate.induction', 'Checks before pouring: induction heats', self::GATE_MODES, 'ENFORCED'],
        'gate_other' => ['pouring.gate.other', 'Checks before pouring: other furnaces', self::GATE_MODES, 'ENFORCED'],
        'gate_packing' => ['pouring.gate.packing', 'Chemistry and thermal analysis checks at packing', self::PACKING_MODES, 'FOLLOW'],
        'first_iron_minutes' => ['pouring.first_iron_minutes', 'Cupola: minutes after the first tap that count as first iron (0 = no first iron)', null, '30'],
        'hold_first_iron' => ['pouring.hold_first_iron', 'Lots made from first iron', [
            'HOLD' => 'Hold them until QC passes',
            'FREE' => 'Treat them like any other lot',
        ], 'HOLD'],
        'lightup_to_first_charge_minutes' => ['charging.lightup_to_first_charge_minutes', 'Cupola: minutes from light-up to the first metal charge (the bed burns first; a recorded blast-on time takes over)', null, '60'],
        'drop_finished_percent' => ['pouring.drop_finished_percent', 'Cupola: percent of the expected run time at the bottom drop that counts as finished without asking (a shorter run asks whether the campaign finished or was dropped early)', null, '90'],
        'test_interval_minutes' => ['pouring.test_interval_minutes', 'Minutes between samples while a cupola is running (reminder only)', null, '30'],
        'auto_charge' => ['charging.auto', 'Charges that come due on a running heat', [
            'AUTO' => 'Record them automatically every minute, from the recipe, at the time the furnace speed puts them due',
            'MANUAL' => 'Wait for someone to confirm each charge (or add them back by hand)',
        ], 'AUTO'],
        'containment' => ['pouring.containment', 'When a sample comes back out of limits', [
            'HOLD' => 'Hold the lots poured in the affected period',
            'NOTE' => 'Only flag the affected pours; do not hold lots',
        ], 'HOLD'],
        'treatment' => ['enforce_treatment_records', 'Treatment before pouring (grades that need it)', [
            'false' => 'Warn: a missing or late treatment is noted on the pour',
            'true' => 'Enforced: no pour without treatment and inoculation in time',
        ], 'false'],
        'max_fade_minutes' => ['max_fade_minutes', 'Minutes from magnesium treatment to pour (fade)', null, '15'],
        'auto_post_returns' => ['auto_post_internal_returns_to_stock', 'Internal returns on a heat charge', [
            'false' => 'Costed in the charge only',
            'true' => 'Posted back to stock as traceable receipts',
        ], 'false'],
    ];

    /** @return list<string> */
    protected static function numberRule(string $rule): array
    {
        return $rule === 'max_fade_minutes' ? ['integer', 'min:1', 'max:120'] : ['integer', 'min:0', 'max:1440'];
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
