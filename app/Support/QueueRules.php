<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\ProductionStageDefinition;
use Illuminate\Support\Str;

/**
 * How the production queue flags work that is piling up, set under Settings: the age at which a step turns amber and red,
 * and an optional work-in-progress limit for each step. A step over its limit shows red whatever its age.
 */
class QueueRules
{
    public const WIP_UNITS = [
        'LOTS' => 'Count lots',
        'PIECES' => 'Count pieces',
    ];

    /** key => [setting, label, options (value => description) or null for a number, default] */
    public const RULES = [
        'amber_hours' => ['queue.amber_hours', 'Hours after the pour when a step turns amber', null, '24'],
        'red_hours' => ['queue.red_hours', 'Hours after the pour when a step turns red', null, '48'],
        'wip_unit' => ['queue.wip_unit', 'Work-in-progress limits', self::WIP_UNITS, 'LOTS'],
    ];

    /**
     * The steps of the queue in process order: waiting for shakeout, after each active stage, ready to pack.
     *
     * @return array<string, string> step key => label
     */
    public static function steps(): array
    {
        $stages = ProductionStageDefinition::where('is_active', true)->orderBy('sequence')->get();
        $steps = ['waiting' => $stages->isEmpty() ? 'Waiting' : 'Waiting for '.Str::lower($stages->first()->name)];
        foreach ($stages as $stage) {
            $steps['after-'.$stage->code] = 'After '.$stage->name;
        }

        return $steps + ['ready' => 'Ready to pack'];
    }

    /**
     * The fixed rules plus one work-in-progress limit per step, in the shape the shared rules page reads.
     *
     * @return array<string, array{0: string, 1: string, 2: array<string, string>|null, 3: string}>
     */
    public static function all(): array
    {
        $rules = self::RULES;
        foreach (self::steps() as $key => $label) {
            $rules['wip_'.$key] = ['queue.wip.'.$key, "Limit: {$label} (0 = no limit)", null, '0'];
        }

        return $rules;
    }

    public static function get(string $rule): string
    {
        // A step's limit is read straight from its key, so the queue does not look the stages up again for every step.
        [$key, , $options, $default] = self::RULES[$rule] ?? ['queue.wip.'.substr($rule, 4), '', null, '0'];
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

    public static function set(string $rule, string $value): void
    {
        [$key, $label] = self::all()[$rule];
        AppSetting::setValue($key, $value, $label);
    }

    public static function amberHours(): int
    {
        return (int) self::get('amber_hours');
    }

    public static function redHours(): int
    {
        return (int) self::get('red_hours');
    }

    public static function countsPieces(): bool
    {
        return self::get('wip_unit') === 'PIECES';
    }

    /** The step's work-in-progress limit, or null when it has none. */
    public static function wipLimit(string $step): ?int
    {
        $limit = (int) self::get('wip_'.$step);

        return $limit > 0 ? $limit : null;
    }
}
