<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * "How you work": plain questions about the foundry, each answer setting one or more process rules. It only fills in the rules,
 * which stay editable one by one on the process depth page.
 */
class ProcessSetup
{
    public const DONE_SETTING = 'setup.process_answered_at';

    /** @var array<string, array{0: string, 1: array<string, array{0: string, 1: array<string, string>}>}> key => [question, answer => [label, rule => value]] */
    public const QUESTIONS = [
        'review' => ['Do you review an order before making it?', [
            'ALWAYS' => ['Always, signed off by sales and quality', ['contract_review' => 'REQUIRED']],
            'WHEN_OFF' => ['Only when something is off: a clean order goes ahead by itself', ['contract_review' => 'AUTO']],
            'NO' => ['No', ['contract_review' => 'SKIP']],
        ]],
        'flow' => ['What should saving an order do?', [
            'STRAIGHT' => ['Send it straight to production once nothing holds it', ['order_flow' => 'SIMPLE']],
            'STEPS' => ['Keep it as a draft; we release production orders ourselves', ['order_flow' => 'FULL']],
        ]],
        'screens' => ['How does the floor record its work?', [
            'QUICK' => ['Typing moulds made and poured into quick grids', ['floor_screens' => 'QUICK']],
            'FULL' => ['On the full screens: moulding runs, mould readiness, pour register', ['floor_screens' => 'FULL']],
        ]],
        'lists' => ['Do you plan each shift on a work list for the moulders?', [
            'YES' => ['Yes, and a list is issued before work starts', ['work_lists' => 'USED', 'start_before_issue' => 'BLOCK']],
            'DRAFT' => ['Yes, but moulders may start before it is issued', ['work_lists' => 'USED', 'start_before_issue' => 'ALLOW']],
            'NO' => ['No', ['work_lists' => 'OFF', 'start_before_issue' => 'ALLOW']],
        ]],
        'assembly' => ['Are moulds assembled as a step of their own?', [
            'AUTO' => ['No, a mould is ready to pour once it is made', ['mould_assembly' => 'AUTO']],
            'MANUAL' => ['Yes, we record closing and checking the moulds', ['mould_assembly' => 'MANUAL']],
        ]],
        'heats' => ['Do you plan heats days ahead?', [
            'YES' => ['Yes, from the orders released', ['melting_plan' => 'USED']],
            'NO' => ['No, we melt for the moulds waiting', ['melting_plan' => 'OFF']],
        ]],
        'pours' => ['How is a pour recorded?', [
            'ONE_STEP' => ['In one go once it is done', ['pour_entry' => 'ONE_STEP']],
            'STEPS' => ['As it happens: planned, started, then completed', ['pour_entry' => 'STEPS']],
        ]],
        'gate' => ['Must chemistry and a thermal test pass before pouring?', [
            'ENFORCED' => ['Yes, a pour is refused without them (a cupola only warns)', ['gate_cupola' => 'ADVISORY', 'gate_induction' => 'ENFORCED', 'gate_other' => 'ENFORCED']],
            'WARN' => ['Warn, but let the pour go ahead', ['gate_cupola' => 'ADVISORY', 'gate_induction' => 'ADVISORY', 'gate_other' => 'ADVISORY']],
            'NO' => ['No', ['gate_cupola' => 'OFF', 'gate_induction' => 'OFF', 'gate_other' => 'OFF']],
        ]],
        'holds' => ['Are first iron and castings from a failed test held for QC?', [
            'HOLD' => ['Yes, held until QC releases them', ['hold_first_iron' => 'HOLD', 'containment' => 'HOLD']],
            'NOTE' => ['No, just noted', ['hold_first_iron' => 'FREE', 'containment' => 'NOTE']],
        ]],
        'lots' => ['How are castings kept together after pouring?', [
            'MOULD' => ['A lot per mould, traced to each mould', ['lot_grouping' => 'MOULD']],
            'POUR' => ['A lot per pour and product', ['lot_grouping' => 'POUR']],
        ]],
        'complete' => ['When is a production order done?', [
            'INSPECTION' => ['When enough castings pass final inspection', ['order_completion' => 'INSPECTION']],
            'POUR' => ['When enough castings are poured', ['order_completion' => 'POUR']],
        ]],
        'ncr' => ['When should rejects raise a nonconformance?', [
            'ANY' => ['For any reject or rework', ['ncr_threshold' => 'ANY']],
            'OVER_5' => ['Above 5% of the pieces checked', ['ncr_threshold' => 'OVER_5']],
            'NEVER' => ['Never; the reject tally is enough', ['ncr_threshold' => 'NEVER']],
        ]],
        'gauges' => ['Using an instrument overdue for calibration', [
            'WARN' => ['Warns', ['overdue_use' => 'WARN']],
            'BLOCK' => ['Is refused', ['overdue_use' => 'BLOCK']],
            'OFF' => ['Is not checked', ['overdue_use' => 'OFF']],
        ]],
    ];

    /** @return array<string, ?string> question => the answer today's rules match, or null when they match none */
    public static function currentAnswers(): array
    {
        $values = ProcessDepth::values();

        return collect(self::QUESTIONS)->map(function ($question) use ($values) {
            foreach ($question[1] as $answer => [, $rules]) {
                if (collect($rules)->every(fn ($value, $rule) => ($values[$rule] ?? null) === $value)) {
                    return $answer;
                }
            }

            return null;
        })->all();
    }

    /**
     * Each rule the answers set, with its label, the value now and the value it will get.
     *
     * @param  array<string, string>  $answers
     * @return array<string, array{label: string, now: string, then: string}>
     */
    public static function changes(array $answers): array
    {
        $rules = ProcessDepth::rules();
        $values = ProcessDepth::values();
        $out = [];
        foreach (self::rulesFor($answers) as $rule => $value) {
            [$label, $options] = $rules[$rule];
            $out[$rule] = ['label' => $label, 'now' => $options[$values[$rule]] ?? $values[$rule], 'then' => $options[$value] ?? $value, 'changes' => $values[$rule] !== $value];
        }

        return $out;
    }

    /** @param  array<string, string>  $answers */
    public static function apply(array $answers): void
    {
        ProcessDepth::save(self::rulesFor($answers));
        AppSetting::setValue(self::DONE_SETTING, now()->toIso8601String(), 'When the "How you work" questions were last answered');
    }

    public static function answered(): bool
    {
        try {
            return filled(AppSetting::getValue(self::DONE_SETTING));
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @param  array<string, string>  $answers
     * @return array<string, string> rule => value
     */
    private static function rulesFor(array $answers): array
    {
        $out = [];
        foreach (self::QUESTIONS as $key => [, $options]) {
            if (isset($answers[$key], $options[$answers[$key]])) {
                $out += $options[$answers[$key]][1];
            }
        }

        return $out;
    }
}
