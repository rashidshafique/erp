<?php

namespace App\Support;

use App\Models\AppSetting;

/** How pouring is controlled, set under Settings. Each rule has a default that suits the furnace type. */
class PouringRules
{
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
        'test_interval_minutes' => ['pouring.test_interval_minutes', 'Minutes between samples while a cupola is running (reminder only)', null, '30'],
        'auto_charge' => ['charging.auto', 'Charges that come due on a running heat', [
            'AUTO' => 'Record them automatically every minute, from the recipe, at the time the furnace speed puts them due',
            'MANUAL' => 'Wait for someone to confirm each charge (or add them back by hand)',
        ], 'AUTO'],
        'containment' => ['pouring.containment', 'When a sample comes back out of limits', [
            'HOLD' => 'Hold the lots poured in the affected period',
            'NOTE' => 'Only flag the affected pours; do not hold lots',
        ], 'HOLD'],
    ];

    public static function get(string $rule): string
    {
        [$key, , $options, $default] = self::RULES[$rule];
        try {
            $value = AppSetting::getValue($key, $default);
        } catch (\Throwable) {
            return $default;
        }
        if ($options === null) {
            return ctype_digit((string) $value) ? (string) $value : $default;
        }

        return isset($options[$value]) ? $value : $default;
    }

    /** Whether due charges are recorded by the scheduler without anyone confirming them. */
    public static function autoCharges(): bool
    {
        return self::get('auto_charge') === 'AUTO';
    }

    public static function set(string $rule, string $value): void
    {
        [$key, $label] = self::RULES[$rule];
        AppSetting::setValue($key, $value, $label);
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
