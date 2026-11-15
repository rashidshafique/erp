<?php

namespace App\Support;

/** Where the lists' Details choice is kept: the session, so it follows the user from list to list until they sign out. */
class ListLayout
{
    public const DETAILS_KEY = 'lists.details';

    public static function details(): bool
    {
        return (bool) session(self::DETAILS_KEY, true);
    }

    public static function setDetails(bool $on): void
    {
        session([self::DETAILS_KEY => $on]);
    }
}
