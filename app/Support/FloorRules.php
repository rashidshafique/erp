<?php

namespace App\Support;

use App\Domains\Core\ModuleRegistry;

/**
 * Which steps the shop floor goes through between a released order and its lots, set through the process depth. Each "full"
 * value keeps the step as its own screen; the other value lets the system do it or leaves it out.
 */
class FloorRules
{
    use RuleSet {
        get as stored;   // the saved choice, whether or not the quick flow pages are on
    }

    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'work_lists' => ['floor.work_lists', 'Moulding work lists', [
            'USED' => 'Used: shifts planned on issued work lists',
            'OFF' => 'Not used: the planner fills the moulds made grid',
        ], 'USED'],
        'mould_assembly' => ['floor.mould_assembly', 'Assembling moulds after moulding', [
            'MANUAL' => 'Own step on the run page (flaskless is always automatic)',
            'AUTO' => 'Automatic when the output is saved',
        ], 'MANUAL'],
        'pour_entry' => ['floor.pour_entry', 'Recording a pour', [
            'STEPS' => 'Step by step: create, add moulds, start, complete',
            'ONE_STEP' => 'One step: heat and moulds in, pour completed with its lots',
        ], 'STEPS'],
        'melting_plan' => ['floor.melting_plan', 'Melting plan', [
            'USED' => 'Used: heats planned from released orders',
            'OFF' => 'Not used: Next heat melts for the moulds waiting',
        ], 'USED'],
        'floor_screens' => ['floor.screens', 'Shop floor screens', [
            'FULL' => 'Full: moulding runs, mould readiness, pour register',
            'QUICK' => 'Quick: moulds made and poured typed into grids',
        ], 'FULL'],
    ];

    /** Rules whose second value relies on the quick flow pages, so they stay on the full value while that module is off. */
    private const NEED_QUICK_FLOW = ['melting_plan' => 'USED', 'floor_screens' => 'FULL', 'work_lists' => 'USED'];

    /** The choice in force: a choice that needs the switched-off quick flow pages falls back to the full one. */
    public static function get(string $rule): string
    {
        return isset(self::NEED_QUICK_FLOW[$rule]) && ! self::quickFlowOn() ? self::NEED_QUICK_FLOW[$rule] : self::stored($rule);
    }

    /** True when the rule's choice needs the quick flow pages, which are switched off. */
    public static function waitsForQuickFlow(string $rule): bool
    {
        return isset(self::NEED_QUICK_FLOW[$rule]) && ! self::quickFlowOn() && self::stored($rule) !== self::NEED_QUICK_FLOW[$rule];
    }

    public static function usesWorkLists(): bool
    {
        return self::get('work_lists') === 'USED';
    }

    public static function assemblesAutomatically(): bool
    {
        return self::get('mould_assembly') === 'AUTO';
    }

    public static function poursInOneStep(): bool
    {
        return self::get('pour_entry') === 'ONE_STEP';
    }

    public static function usesMeltingPlan(): bool
    {
        return self::get('melting_plan') === 'USED';
    }

    public static function usesQuickScreens(): bool
    {
        return self::get('floor_screens') === 'QUICK';
    }

    private static function quickFlowOn(): bool
    {
        try {
            return app(ModuleRegistry::class)->isEnabled('quickflow');
        } catch (\Throwable) {
            return false;
        }
    }
}
