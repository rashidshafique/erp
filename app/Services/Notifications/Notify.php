<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\AppNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/** Sends a notice to the people who should see it: everyone holding a permission, or everyone in named roles. */
class Notify
{
    /** The hat a permission stands for, as shown on a notice someone gets for more than one. */
    public const HATS = [
        'planning.manage' => 'Planning', 'melting.operate' => 'Melt shop', 'moulding.operate' => 'Moulding',
        'quality.inspect' => 'Quality', 'stores.manage' => 'Stores', 'purchasing.manage' => 'Purchasing', 'sales.manage' => 'Sales',
    ];

    /** Active users who hold the permission, administrators included. @return Collection<int, User> */
    public function usersWithPermission(string $permission): Collection
    {
        return User::where('is_active', true)->with('roles')->get()->filter(fn (User $user) => $user->hasPermission($permission))->values();
    }

    /**
     * Active users in any of the roles, by role code.
     *
     * @param  list<string>  $roleCodes
     * @return Collection<int, User>
     */
    public function usersInRoles(array $roleCodes): Collection
    {
        return User::where('is_active', true)->whereHas('roles', fn ($q) => $q->whereIn('code', $roleCodes))->get();
    }

    public function toPermission(string $permission, AppNotice $notice): int
    {
        return $this->send($this->usersWithPermission($permission), $notice);
    }

    /**
     * Everyone holding any of the permissions, counted once, except the person who did the thing. A one-person foundry never
     * gets a notice about its own step.
     *
     * @param  list<string>  $permissions
     */
    public function toPermissions(array $permissions, AppNotice $notice, ?User $by = null): int
    {
        $hats = [];
        foreach ($permissions as $permission) {
            foreach ($this->usersWithPermission($permission) as $user) {
                if ($by !== null && $user->id === $by->id) {
                    continue;
                }
                $hats[$user->id] ??= ['user' => $user, 'for' => []];
                $hats[$user->id]['for'][] = self::HATS[$permission] ?? $permission;
            }
        }

        // People holding the same hats get one send; someone with two hats sees both on the notice.
        foreach (collect($hats)->groupBy(fn (array $h) => implode('|', $h['for'])) as $group) {
            $this->send($group->pluck('user'), $notice->forHats($group->first()['for']));
        }

        return count($hats);
    }

    /** @param  list<string>  $roleCodes */
    public function toRoles(array $roleCodes, AppNotice $notice): int
    {
        return $this->send($this->usersInRoles($roleCodes), $notice);
    }

    /** @param  Collection<int, User>  $users */
    private function send(Collection $users, AppNotice $notice): int
    {
        if ($users->isNotEmpty()) {
            Notification::send($users, $notice);
        }

        return $users->count();
    }
}
