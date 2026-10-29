<?php

namespace App\Support;

use App\Domains\Development\Support\DevelopmentRules;
use App\Domains\Maintenance\Support\CalibrationRules;

/**
 * One choice for how much checking the shop floor goes through. Basic is for small foundries that want few screens and quick
 * entry; Full keeps every check in force. Each preset just sets the individual rules, which stay editable on their own pages.
 */
class ProcessDepth
{
    /** label and description of each preset */
    public const PRESETS = [
        'BASIC' => ['Basic', 'Fast entry for a small foundry: a saved order goes straight to production; moulds made and poured are typed into the quick grids, with no work lists or melting plan; moulds are assembled as they are made and a pour is recorded, completed and given its lots in one step; no pouring gates, first-iron holds or calibration blocks; an order completes when its castings are poured, one lot per pour and product, and the quick QC tally raises a nonconformance only above 5% rejects.'],
        'FULL' => ['Full', 'Every step and check in force: the full shop floor screens, contract review, work lists issued before runs start, the melting plan, mould assembly and pours step by step, pouring gates, first-iron and containment holds, calibration warnings, and completion after final inspection.'],
    ];

    /** @var array<string, array<string, string>> rule => value, per preset */
    private const VALUES = [
        'BASIC' => ['contract_review' => 'SKIP', 'order_flow' => 'SIMPLE', 'order_completion' => 'POUR',
            'floor_screens' => 'QUICK', 'work_lists' => 'OFF', 'start_before_issue' => 'ALLOW', 'mould_assembly' => 'AUTO', 'melting_plan' => 'OFF', 'pour_entry' => 'ONE_STEP', 'gate_cupola' => 'OFF', 'gate_induction' => 'OFF', 'gate_other' => 'OFF', 'hold_first_iron' => 'FREE', 'containment' => 'NOTE',
            'overdue_use' => 'OFF', 'ncr_threshold' => 'OVER_5', 'lot_grouping' => 'POUR'],
        'FULL' => ['contract_review' => 'REQUIRED', 'order_flow' => 'FULL', 'order_completion' => 'INSPECTION',
            'floor_screens' => 'FULL', 'work_lists' => 'USED', 'start_before_issue' => 'BLOCK', 'mould_assembly' => 'MANUAL', 'melting_plan' => 'USED', 'pour_entry' => 'STEPS', 'gate_cupola' => 'ADVISORY', 'gate_induction' => 'ENFORCED', 'gate_other' => 'ENFORCED', 'hold_first_iron' => 'HOLD', 'containment' => 'HOLD',
            'overdue_use' => 'WARN', 'ncr_threshold' => 'ANY', 'lot_grouping' => 'MOULD'],
    ];

    /** Where each rule lives: the class that reads and writes it. */
    private const CLASSES = [
        'development_open' => DevelopmentRules::class,
        'contract_review' => OrderRules::class, 'order_flow' => OrderRules::class, 'order_completion' => OrderRules::class,
        'work_lists' => FloorRules::class, 'start_before_issue' => WorkListRules::class, 'mould_assembly' => FloorRules::class, 'floor_screens' => FloorRules::class,
        'melting_plan' => FloorRules::class, 'pour_entry' => FloorRules::class,
        'gate_cupola' => PouringRules::class, 'gate_induction' => PouringRules::class, 'gate_other' => PouringRules::class,
        'hold_first_iron' => PouringRules::class, 'containment' => PouringRules::class,
        'overdue_use' => CalibrationRules::class, 'ncr_threshold' => QualityRules::class, 'lot_grouping' => QualityRules::class, 'qc_after_steps' => QualityRules::class,
    ];

    /** The rules page's sections, in the order work flows through the foundry. */
    public const GROUPS = [
        'Enquiries' => ['development_open'],
        'Orders' => ['contract_review', 'order_flow', 'order_completion'],
        'Moulding' => ['floor_screens', 'work_lists', 'start_before_issue', 'mould_assembly'],
        'Melting and pouring' => ['melting_plan', 'pour_entry', 'gate_cupola', 'gate_induction', 'gate_other', 'hold_first_iron', 'containment'],
        'Quality and gauges' => ['lot_grouping', 'qc_after_steps', 'ncr_threshold', 'overdue_use'],
    ];

    public static function apply(string $preset): void
    {
        foreach (self::VALUES[$preset] as $rule => $value) {   // rules a preset leaves out keep their value
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
        foreach (array_merge(...array_values(self::GROUPS)) as $rule) {
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
        return self::CLASSES[$rule]::RULES[$rule];
    }

    /** The stored value, so a choice that waits for a switched-off module still shows as chosen. */
    private static function get(string $rule): string
    {
        $class = self::CLASSES[$rule];

        return $class === FloorRules::class ? FloorRules::stored($rule) : $class::get($rule);
    }

    private static function set(string $rule, string $value): void
    {
        self::CLASSES[$rule]::set($rule, $value);
    }
}
