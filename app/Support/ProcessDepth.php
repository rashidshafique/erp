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
        'BASIC' => ['Basic', 'Fast entry for a small foundry: no pouring gates, first-iron holds or calibration blocks, and an order completes when its castings are poured.'],
        'FULL' => ['Full', 'Every check in force: pouring gates, first-iron and containment holds, calibration warnings, contract review, and completion after final inspection.'],
    ];

    /** @var array<string, array<string, array{0: string, 1: string}>> rule => [class, rule key] => value, per preset */
    private const VALUES = [
        'BASIC' => ['gate_cupola' => 'OFF', 'gate_induction' => 'OFF', 'gate_other' => 'OFF', 'hold_first_iron' => 'FREE', 'containment' => 'NOTE', 'overdue_use' => 'OFF', 'contract_review' => 'SKIP', 'order_completion' => 'POUR'],
        'FULL' => ['gate_cupola' => 'ADVISORY', 'gate_induction' => 'ENFORCED', 'gate_other' => 'ENFORCED', 'hold_first_iron' => 'HOLD', 'containment' => 'HOLD', 'overdue_use' => 'WARN', 'contract_review' => 'REQUIRED', 'order_completion' => 'INSPECTION'],
    ];

    public static function apply(string $preset): void
    {
        foreach (self::VALUES[$preset] as $rule => $value) {
            self::set($rule, $value);
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

    private static function get(string $rule): string
    {
        return match ($rule) {
            'overdue_use' => CalibrationRules::get($rule),
            'contract_review', 'order_completion' => OrderRules::get($rule),
            default => PouringRules::get($rule),
        };
    }

    private static function set(string $rule, string $value): void
    {
        match ($rule) {
            'overdue_use' => CalibrationRules::set($rule, $value),
            'contract_review', 'order_completion' => OrderRules::set($rule, $value),
            default => PouringRules::set($rule, $value),
        };
    }
}
