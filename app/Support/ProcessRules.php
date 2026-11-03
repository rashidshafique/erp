<?php

namespace App\Support;

use App\Domains\Core\ModuleRegistry;

/**
 * Settings > Process rules: every foundry-wide rule on one page, each shown once, in sections that follow the work through
 * the foundry. Modules add their own sections; a switched-off module's rules are left out.
 */
class ProcessRules
{
    /** @var array<string, array<class-string, list<string>|null>> section => [rules class => the rules shown here, or null for all] */
    private const SECTIONS = [
        'Orders' => [OrderRules::class => null],
        'Moulding' => [FloorRules::class => ['floor_screens', 'work_lists', 'mould_assembly'], WorkListRules::class => null],
        'Flasks' => [FlaskRules::class => null],
        'Melting and pouring' => [FloorRules::class => ['melting_plan', 'pour_entry'], PouringRules::class => null],
        'Production queue' => [QueueRules::class => null],
        'Quality' => [QualityRules::class => null, ControlPlanRules::class => null],
        'Patterns' => [PatternOwnershipRules::class => null],
    ];

    /** Section order on the page, so module sections sit where their work happens; any other section goes last. */
    private const ORDER = ['Development', 'Orders', 'Moulding', 'Cores', 'Flasks', 'Melting and pouring', 'Production queue', 'Quality', 'Calibration', 'Patterns', 'Purchasing'];

    /**
     * Section => rule => its label, choices and saved value. The common view holds the rules the presets and How you work set
     * (ProcessDepth::numbers); the advanced view adds every other rule, and a section left with no rules drops out.
     *
     * @return array<string, array<string, array{class: class-string, label: string, options: array<string, string>|null, value: string}>>
     */
    public static function sections(bool $advanced = false): array
    {
        $sections = self::SECTIONS;
        foreach (app(ModuleRegistry::class)->ruleSections() as $section => $classes) {
            foreach ($classes as $class) {
                $sections[$section][$class] = null;
            }
        }
        uksort($sections, fn (string $a, string $b) => self::position($a) <=> self::position($b));

        $out = [];
        foreach ($sections as $section => $classes) {
            foreach ($classes as $class => $keys) {
                $definitions = $class::rules();
                foreach ($keys ?? array_keys($definitions) as $rule) {
                    if (! $advanced && ProcessDepth::number($rule) === null) {
                        continue;
                    }
                    $out[$section][$rule] = [
                        'class' => $class, 'label' => $definitions[$rule][1], 'options' => $definitions[$rule][2],
                        'value' => $class === FloorRules::class ? FloorRules::stored($rule) : $class::get($rule),
                    ];
                }
            }
        }

        return $out;
    }

    /** @return array<string, list<mixed>> rule => validation, for the rules on the page */
    public static function validation(): array
    {
        $rules = [];
        foreach (self::classes() as $class => $keys) {
            $rules += array_intersect_key($class::validation(), array_flip($keys));
        }

        return $rules;
    }

    /** @param array<string, mixed> $values validated rule => value; rules not sent keep their value */
    public static function save(array $values): void
    {
        foreach (self::classes() as $class => $keys) {
            foreach ($keys as $rule) {
                if (array_key_exists($rule, $values)) {
                    $class::set($rule, (string) ($values[$rule] ?? 0));   // a blank number is 0
                }
            }
        }
    }

    /** @return array<class-string, list<string>> each rules class with the rules shown for it */
    private static function classes(): array
    {
        $classes = [];
        foreach (self::sections(true) as $rules) {
            foreach ($rules as $rule => $definition) {
                $classes[$definition['class']][] = $rule;
            }
        }

        return $classes;
    }

    private static function position(string $section): int
    {
        $index = array_search($section, self::ORDER, true);

        return $index === false ? PHP_INT_MAX : $index;
    }
}
