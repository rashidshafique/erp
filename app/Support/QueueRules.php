<?php

namespace App\Support;

use App\Models\ProductionStageDefinition;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * How the production queue flags work that is piling up, set under Settings: the age at which a step turns amber and red,
 * and an optional work-in-progress limit for each step. A step over its limit shows red whatever its age.
 */
class QueueRules
{
    use RuleSet;

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

    /** @return array<string, array{0: string, 1: string, 2: array<string, string>|null, 3: string}> */
    public static function rules(): array
    {
        return self::all();
    }

    /** @return array<string, list<mixed>> */
    public static function validation(): array
    {
        return array_merge(collect(self::all())->map(fn (array $definition, string $rule) => $definition[2] === null
            ? ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000']   // a blank limit is no limit
            : ['sometimes', 'required', Rule::in(array_keys($definition[2]))])->all(),
            ['amber_hours' => ['sometimes', 'required', 'integer', 'min:1', 'max:2000'], 'red_hours' => ['sometimes', 'required', 'integer', 'min:1', 'max:2000', 'gt:amber_hours']]);
    }

    /** A step's limit is read straight from its key, so the queue does not look the stages up again for every step. */
    protected static function definition(string $rule): array
    {
        return self::RULES[$rule] ?? ['queue.wip.'.substr($rule, 4), 'Work-in-progress limit', null, '0'];
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
