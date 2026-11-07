<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\AccessMap;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login and permission check for every web request, when foundry.require_login is on.
 * Pages are readable by any signed-in user unless the route map marks reads. Changes need the mapped permission;
 * a change route nobody mapped is administrators-only (fail closed).
 */
class EnforceAccess
{
    private const OPEN = ['login', 'login.attempt', 'logout', 'account.password', 'account.password.update'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('foundry.require_login')) {
            return $next($request);
        }

        $name = (string) $request->route()?->getName();
        if (in_array($name, ['login', 'login.attempt', 'm.login', 'branding.logo'], true)) {
            return $next($request);
        }

        $user = Auth::user();
        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sign in again.'], 401);
            }

            return redirect()->guest(route('login'));
        }
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            return redirect()->route('login')->withErrors(['email' => 'This account is switched off.']);
        }
        if (in_array($name, self::OPEN, true)) {
            return $next($request);
        }
        if ($user->must_change_password) {
            return redirect()->route('account.password')->with('success', 'Set a new password before continuing.');
        }
        if ($user->isAdmin()) {
            return $next($request);
        }

        foreach (config('permissions.open_routes', []) as $open) {
            if (Str::is($open, $name)) {
                return $next($request);
            }
        }
        if (Str::endsWith($name, 'livewire.update')) {
            return self::livewireAllows($user, (array) $request->input('components', []))
                ? $next($request) : $this->deny($request, 'This action is not open to your role.');
        }

        $isRead = $request->isMethodSafe();
        $required = self::requiredPermission($name);

        if ($required === null) {
            if ($isRead) {
                return $next($request);
            }
            return $this->deny($request, 'This action is not open to your role.');
        }
        if ($isRead && ! $required['reads']) {
            return $next($request);
        }
        if (! self::holds($user, $required, AccessMap::actionOf($request->method(), $name, $required['rule']))) {
            return $this->deny($request, 'Your role does not include "'.(config('permissions.catalog')[$required['permission']] ?? $required['permission']).'".');
        }

        return $next($request);
    }

    /** Livewire's own calls that never change data: paging and refreshing. */
    private const LIVEWIRE_READS = ['$refresh', 'gotoPage', 'nextPage', 'previousPage', 'setPage', 'resetPage'];

    /**
     * Whether a Livewire request may run, by the permissions.livewire map: every component must be listed, and a component
     * whose calls or changed properties go beyond its read-only ones needs its route's permission to change data.
     *
     * @param  list<array{snapshot?: string, updates?: array<string, mixed>, calls?: list<array{method?: string}>}>  $components
     */
    public static function livewireAllows(\App\Models\User $user, array $components): bool
    {
        $map = config('permissions.livewire', []);
        foreach ($components as $component) {
            $name = json_decode((string) ($component['snapshot'] ?? ''), true)['memo']['name'] ?? null;
            if (! is_string($name) || ! array_key_exists($name, $map)) {
                return false;
            }
            [$route, $reads, $filters] = $map[$name] + [null, [], []];
            if ($route === null) {
                continue;
            }
            $calls = collect($component['calls'] ?? [])->pluck('method')->reject(fn ($m) => in_array($m, [...self::LIVEWIRE_READS, ...$reads], true));
            $changes = collect(array_keys((array) ($component['updates'] ?? [])))->map(fn (string $key) => Str::before($key, '.'))->reject(fn ($p) => in_array($p, $filters, true));
            if ($calls->isEmpty() && $changes->isEmpty()) {
                continue;
            }
            $required = self::requiredPermission($route);
            if ($required === null || ! self::holds($user, $required, 'edit')) {
                return false;
            }
        }

        return true;
    }

    /** @param  array{permission:string,also:list<string>,area:?string}  $required */
    private static function holds(\App\Models\User $user, array $required, string $action): bool
    {
        return $user->is_active && ($user->isAdmin() || AccessMap::covers($user->permissions(), $required, $action));
    }

    /** @return array{permission:string,reads:bool,also:list<string>,area:?string,rule:array<string,mixed>}|null */
    public static function requiredPermission(string $routeName): ?array
    {
        foreach (config('permissions.routes', []) as $rule) {
            if (Str::is($rule['pattern'], $routeName)) {
                return ['permission' => $rule['permission'], 'reads' => (bool) ($rule['reads'] ?? false) || in_array(AccessMap::areaOf($rule), AccessMap::viewLimited(), true), 'also' => $rule['also'] ?? [], 'area' => AccessMap::areaOf($rule), 'rule' => $rule];
            }
        }
        return null;
    }

    /** Whether the signed-in user may use a route, by the same map as the check above; for screens that show or hide a control. */
    public static function allows(?\App\Models\User $user, string $routeName): bool
    {
        if (! config('foundry.require_login')) {
            return true;
        }
        if (! $user || ! $user->is_active) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        $required = self::requiredPermission($routeName);

        $method = Route::getRoutes()->getByName($routeName)?->methods()[0] ?? 'GET';

        return $required !== null && self::holds($user, $required, AccessMap::actionOf($method, $routeName, $required['rule']));
    }

    private function deny(Request $request, string $why): Response
    {
        return response()->view('errors.403', ['why' => $why], 403);
    }
}
