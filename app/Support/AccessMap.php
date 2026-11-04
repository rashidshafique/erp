<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Splits each permission in `config/permissions.php` into the actions people can be given separately. A rule's route pattern names
 * an area (`heats.*` is the area `heats`); the verb and the end of a route's name give the action. A role holds either the whole
 * permission (every action of every area under it, as before) or single `area:action` grants.
 */
class AccessMap
{
    public const ACTIONS = ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'approve' => 'Approve'];

    /** Ends of a route name that carry weight beyond an edit: voiding, closing, issuing, posting, releasing. */
    private const APPROVE = ['void', 'close', 'release', 'reopen', 'approve', 'decide', 'issue', 'post', 'reverse', 'cancel', 'hold', 'lock', 'complete', 'finish', 'confirm', 'reject'];

    private const DELETE = ['destroy', 'delete', 'remove', 'detach', 'discard'];

    private const CREATE = ['store', 'create', 'add', 'copy', 'upload', 'import', 'duplicate'];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $tree = null;

    /** The area a rule belongs to: named in the rule, else the first part of its pattern. */
    public static function areaOf(array $rule): ?string
    {
        if (($rule['area'] ?? null) === false) {
            return null;
        }

        return $rule['area'] ?? (string) Str::of($rule['pattern'])->before('.')->before('*')->rtrim('-');
    }

    /** The action a request makes: the rule's own, else from the end of the route name, else from the verb. */
    public static function actionOf(string $method, string $routeName, ?array $rule = null): string
    {
        if (! empty($rule['action'])) {
            return $rule['action'];
        }
        if (in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return 'view';
        }
        $end = Str::afterLast($routeName, '.');
        foreach ([['approve', self::APPROVE], ['delete', self::DELETE], ['create', self::CREATE]] as [$action, $ends]) {
            if (in_array($end, $ends, true) || (strtoupper($method) === 'DELETE' && $action === 'delete')) {
                return $action;
            }
        }

        return 'edit';
    }

    public static function key(string $area, string $action): string
    {
        return $area.':'.$action;
    }

    /**
     * Whether a user's grants cover an action. A grant of the whole permission covers every action under it; a single grant covers
     * its own action, and any change also lets the person view.
     *
     * @param  list<string>  $held
     * @param  array{permission:string,area?:string,also?:list<string>}  $required
     */
    public static function covers(array $held, array $required, string $action): bool
    {
        $options = [$required['permission'], ...($required['also'] ?? [])];
        if (array_intersect($options, $held)) {
            return true;
        }
        $area = $required['area'] ?? null;
        if ($area === null) {
            return false;
        }
        $needs = $action === 'view' ? array_keys(self::ACTIONS) : [$action];

        return (bool) array_intersect(array_map(fn ($a) => self::key($area, $a), $needs), $held);
    }

    /** Whether a held permission list lets someone change anything under a whole permission, for screens that only ask the whole one. */
    public static function partlyHolds(string $permission, array $held): bool
    {
        if (! collect($held)->contains(fn (string $p) => str_contains($p, ':'))) {
            return false;
        }
        $areas = array_keys(self::tree()[$permission]['areas'] ?? []);
        foreach ($areas as $area) {
            foreach (['create', 'edit', 'delete', 'approve'] as $action) {
                if (in_array(self::key($area, $action), $held, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Every permission with the areas under it and the actions each area has, from the rule list and the named routes.
     *
     * @return array<string, array{label:string, areas: array<string, array{label:string, actions:list<string>}>}>
     */
    public static function tree(): array
    {
        if (self::$tree !== null) {
            return self::$tree;
        }
        $catalog = config('permissions.catalog', []);
        $rules = config('permissions.routes', []);
        $tree = [];
        foreach ($rules as $rule) {
            if (($area = self::areaOf($rule)) === null) {
                continue;
            }
            $tree[$rule['permission']]['label'] ??= $catalog[$rule['permission']] ?? $rule['permission'];
            $tree[$rule['permission']]['areas'][$area] ??= ['label' => (string) ($rule['label'] ?? Str::headline($area)), 'actions' => ['view']];
        }
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            foreach ($rules as $rule) {
                if (! Str::is($rule['pattern'], $name)) {
                    continue;
                }
                if (self::areaOf($rule) === null) {
                    break;
                }
                $action = self::actionOf($route->methods()[0], $name, $rule);
                $actions = &$tree[$rule['permission']]['areas'][self::areaOf($rule)]['actions'];
                if (! in_array($action, $actions, true)) {
                    $actions[] = $action;
                }
                unset($actions);
                break;
            }
        }
        foreach ($tree as &$group) {
            ksort($group['areas']);
            foreach ($group['areas'] as &$area) {
                $area['actions'] = array_values(array_intersect(array_keys(self::ACTIONS), $area['actions']));
            }
            unset($area);
        }
        unset($group);

        return self::$tree = $tree;
    }

    /**
     * Areas whose pages need a View grant even though the permission map leaves them open to every signed-in user.
     *
     * @return list<string>
     */
    public static function viewLimited(): array
    {
        return (array) json_decode((string) \App\Models\AppSetting::getValue('access.view_limited', '[]'), true);
    }

    /** @param  list<string>  $areas */
    public static function limitViewing(array $areas): void
    {
        $valid = collect(self::tree())->flatMap(fn ($g) => array_keys($g['areas']))->all();
        \App\Models\AppSetting::setValue('access.view_limited', json_encode(array_values(array_intersect($areas, $valid))), 'Areas whose pages need a View grant');
    }

    public static function flush(): void
    {
        self::$tree = null;
    }
}
