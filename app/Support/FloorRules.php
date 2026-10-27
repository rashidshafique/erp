<?php

namespace App\Support;

use App\Domains\Core\ModuleRegistry;
use App\Models\AppSetting;

/**
 * Which steps the shop floor goes through between a released order and its lots, set through the process depth. Each "full"
 * value keeps the step as its own screen; the other value lets the system do it or leaves it out.
 */
class FloorRules
{
    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'work_lists' => ['floor.work_lists', 'Moulding work lists', [
            'USED' => 'Used: shifts are planned on work lists, which are issued to the teams',
            'OFF' => 'Not used: the planner\'s first shift fills the moulds made grid, and work lists leave the menu',
        ], 'USED'],
        'mould_assembly' => ['floor.mould_assembly', 'Assembling moulds after moulding', [
            'MANUAL' => 'A step of its own: accepted moulds are assembled on the run page (flaskless moulds are always automatic)',
            'AUTO' => 'Automatic: every accepted mould is assembled as soon as the output is saved',
        ], 'MANUAL'],
        'pour_entry' => ['floor.pour_entry', 'Recording a pour', [
            'STEPS' => 'Step by step: create the pour, add moulds, start it, then complete it',
            'ONE_STEP' => 'One step: pick the heat and the moulds poured, and the pour is recorded, completed and its lots made',
        ], 'STEPS'],
        'melting_plan' => ['floor.melting_plan', 'Melting plan', [
            'USED' => 'Used: heats are planned ahead from released orders',
            'OFF' => 'Not used: Next heat says what to melt from the moulds waiting, and the melting plan leaves the menu',
        ], 'USED'],
        'floor_screens' => ['floor.screens', 'Shop floor screens', [
            'FULL' => 'Full screens: moulding runs, mould readiness and the pour register',
            'QUICK' => 'Quick screens: moulds made and poured are typed into grids, and the full screens leave the menu',
        ], 'FULL'],
    ];

    /** Rules whose second value relies on the quick flow pages, so they stay on the full value while that module is off. */
    private const NEED_QUICK_FLOW = ['melting_plan' => 'USED', 'floor_screens' => 'FULL', 'work_lists' => 'USED'];

    public static function get(string $rule): string
    {
        [$key, , $options, $default] = self::RULES[$rule];
        try {
            $value = AppSetting::getValue($key, $default);
        } catch (\Throwable) {
            return $default;
        }
        $value = isset($options[$value]) ? $value : $default;

        return isset(self::NEED_QUICK_FLOW[$rule]) && ! self::quickFlowOn() ? self::NEED_QUICK_FLOW[$rule] : $value;
    }

    /** The stored choice, ignoring whether the quick flow pages are on, for the settings page. */
    public static function stored(string $rule): string
    {
        [$key, , $options, $default] = self::RULES[$rule];
        try {
            $value = AppSetting::getValue($key, $default);
        } catch (\Throwable) {
            return $default;
        }

        return isset($options[$value]) ? $value : $default;
    }

    public static function set(string $rule, string $value): void
    {
        [$key, $label] = self::RULES[$rule];
        AppSetting::setValue($key, $value, $label);
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
