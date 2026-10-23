<?php

namespace App\Support;

use App\Domains\Maintenance\Support\CalibrationRules;

/**
 * One choice for how much checking the shop floor goes through. Basic is for small foundries that want few screens and quick
 * entry; Full keeps every check in force. Each preset just sets the individual rules, which stay editable on their own pages.
 */
class ProcessDepth
{
    /** label and description of each preset */
    public const PRESETS = [
        'BASIC' => ['Basic', 'Fast entry for a small foundry: no pouring gates, first-iron holds or calibration blocks, a saved order goes straight to production, an order completes when its castings are poured, one lot per pour and product, and the quick QC tally raises a nonconformance only above 5% rejects.'],
        'FULL' => ['Full', 'Every check in force: pouring gates, first-iron and containment holds, calibration warnings, contract review, and completion after final inspection.'],
    ];

    /** @var array<string, array<string, array{0: string, 1: string}>> rule => [class, rule key] => value, per preset */
    private const VALUES = [
        'BASIC' => ['gate_cupola' => 'OFF', 'gate_induction' => 'OFF', 'gate_other' => 'OFF', 'hold_first_iron' => 'FREE', 'containment' => 'NOTE', 'overdue_use' => 'OFF', 'contract_review' => 'SKIP', 'order_flow' => 'SIMPLE', 'order_completion' => 'POUR', 'ncr_threshold' => 'OVER_5', 'lot_grouping' => 'POUR'],
        'FULL' => ['gate_cupola' => 'ADVISORY', 'gate_induction' => 'ENFORCED', 'gate_other' => 'ENFORCED', 'hold_first_iron' => 'HOLD', 'containment' => 'HOLD', 'overdue_use' => 'WARN', 'contract_review' => 'REQUIRED', 'order_flow' => 'FULL', 'order_completion' => 'INSPECTION', 'ncr_threshold' => 'ANY', 'lot_grouping' => 'MOULD'],
    ];

    public static function apply(string $preset): void
    {
        foreach (self::VALUES[$preset] as $rule => $value) {
            self::set($rule, $value);
        }
    }

    /**
     * Every rule a preset sets, with its label and choices, so each can be set on its own.
     *
     * @return array<string, array{0: string, 1: array<string, string>}> rule => [label, options]
     */
    public static function rules(): array
    {
        $out = [];
        foreach (array_keys(self::VALUES['FULL']) as $rule) {
            $definition = self::definition($rule);
            $out[$rule] = [$definition[1], $definition[2]];
        }

        return $out;
    }

    /** @return array<string, string> rule => current value */
    public static function values(): array
    {
        return collect(self::rules())->map(fn ($r, $rule) => self::get($rule))->all();
    }

    /** @param array<string, string> $values rule => chosen value; unknown rules and choices are ignored */
    public static function save(array $values): void
    {
        foreach (self::rules() as $rule => [, $options]) {
            if (isset($values[$rule]) && array_key_exists($values[$rule], $options)) {
                self::set($rule, $values[$rule]);
            }
        }
    }

    /** BASIC or FULL when every rule matches that preset, otherwise CUSTOM. */
    public static function current(): string
    {
        foreach (array_keys(self::VALUES) as $preset) {
            if (collect(self::VALUES[$preset])->every(fn ($value, $rule) => self::get($rule) === $value)) {
                return $preset;
            }
        }

        return 'CUSTOM';
    }

    private static function definition(string $rule): array
    {
        return match ($rule) {
            'overdue_use' => CalibrationRules::RULES[$rule],
            'contract_review', 'order_flow', 'order_completion' => OrderRules::RULES[$rule],
            'ncr_threshold', 'lot_grouping' => QualityRules::RULES[$rule],
            default => PouringRules::RULES[$rule],
        };
    }

    private static function get(string $rule): string
    {
        return match ($rule) {
            'overdue_use' => CalibrationRules::get($rule),
            'contract_review', 'order_flow', 'order_completion' => OrderRules::get($rule),
            'ncr_threshold', 'lot_grouping' => QualityRules::get($rule),
            default => PouringRules::get($rule),
        };
    }

    private static function set(string $rule, string $value): void
    {
        match ($rule) {
            'overdue_use' => CalibrationRules::set($rule, $value),
            'contract_review', 'order_flow', 'order_completion' => OrderRules::set($rule, $value),
            'ncr_threshold', 'lot_grouping' => QualityRules::set($rule, $value),
            default => PouringRules::set($rule, $value),
        };
    }
}
