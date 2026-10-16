<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if (in_array($name, ['login', 'login.attempt', 'branding.logo'], true)) {
            return $next($request);
        }

        $user = Auth::user();
        if (! $user) {
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
        if (! $user->hasPermission($required['permission'])) {
            return $this->deny($request, 'Your role does not include "'.(config('permissions.catalog')[$required['permission']] ?? $required['permission']).'".');
        }

        return $next($request);
    }

    /** @return array{permission:string,reads:bool}|null */
    public static function requiredPermission(string $routeName): ?array
    {
        foreach (config('permissions.routes', []) as $rule) {
            if (Str::is($rule['pattern'], $routeName)) {
                return ['permission' => $rule['permission'], 'reads' => (bool) ($rule['reads'] ?? false)];
            }
        }
        return null;
    }

    private function deny(Request $request, string $why): Response
    {
        return response()->view('errors.403', ['why' => $why], 403);
    }
}
