<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Validation\Rule;

/**
 * A set of foundry-wide rules kept in app settings. The using class lists them in RULES as
 * key => [setting, label, options (value => text) or null for a number, default].
 */
trait RuleSet
{
    /** @return array<string, array{0: string, 1: string, 2: array<string, string>|null, 3: string}> */
    public static function rules(): array
    {
        return static::RULES;
    }

    /** The saved value, or the default when nothing valid is saved. */
    public static function get(string $rule): string
    {
        [$key, , $options, $default] = static::definition($rule);
        $default = static::defaultFor($rule, $default);
        try {
            $value = AppSetting::getValue($key, $default) ?? $default;
        } catch (\Throwable) {
            return $default;
        }
        if ($options === null) {
            return is_numeric($value) && $value >= 0 ? (string) $value : $default;
        }

        return isset($options[$value]) ? (string) $value : $default;
    }

    public static function set(string $rule, string $value): void
    {
        [$key, $label] = static::definition($rule);
        AppSetting::setValue($key, $value, $label);
    }

    /** @return array<string, string> rule => current value */
    public static function values(): array
    {
        return collect(static::rules())->map(fn (array $definition, string $rule) => static::get($rule))->all();
    }

    /** @return array<string, list<mixed>> rule => validation rules for a form that may send any of them */
    public static function validation(): array
    {
        return collect(static::rules())->map(fn (array $definition, string $rule) => $definition[2] === null
            ? ['sometimes', 'required', ...static::numberRule($rule)]
            : ['sometimes', 'required', Rule::in(array_keys($definition[2]))])->all();
    }

    /** @return array{0: string, 1: string, 2: array<string, string>|null, 3: string} */
    protected static function definition(string $rule): array
    {
        return static::RULES[$rule];
    }

    /** The value the rule has until someone changes it. */
    public static function defaultOf(string $rule): string
    {
        return static::defaultFor($rule, static::definition($rule)[3]);
    }

    protected static function defaultFor(string $rule, string $default): string
    {
        return $default;
    }

    /** @return list<string> validation for a number rule */
    protected static function numberRule(string $rule): array
    {
        return ['integer', 'min:0', 'max:100000'];
    }
}
