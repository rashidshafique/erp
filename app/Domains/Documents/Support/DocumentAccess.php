<?php

namespace App\Domains\Documents\Support;

use App\Models\User;

/** Who may do what with controlled documents. With sign-in switched off, everyone is the administrator. */
class DocumentAccess
{
    public static function manages(?User $user): bool
    {
        return ! config('foundry.require_login') || ($user !== null && $user->hasPermission('documents.manage'));
    }

    public static function approves(?User $user): bool
    {
        return ! config('foundry.require_login') || ($user !== null && $user->hasPermission('documents.approve'));
    }

    /** Drafts, documents in review and old revisions are for those who write or approve documents. Everyone else sees what is in force. */
    public static function seesEverything(?User $user): bool
    {
        return self::manages($user) || self::approves($user);
    }
}
