<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Who may see the money on a screen. Prices (what we sell and buy for) and costs (what things cost us, margins) are permissions of
 * their own, so a role can work on orders, stock or heats without seeing them. Use `@fieldcan('costs') ... @endfieldcan` in a view.
 */
class Fields
{
    public const KINDS = ['prices' => 'fields.prices', 'costs' => 'fields.costs'];

    public static function can(string $kind): bool
    {
        if (! config('foundry.require_login')) {
            return true;
        }

        return (bool) Auth::user()?->hasPermission(self::KINDS[$kind] ?? $kind);
    }
}
