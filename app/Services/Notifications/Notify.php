<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\AppNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/** Sends a notice to the people who should see it: everyone holding a permission, or everyone in named roles. */
class Notify
{
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
        $users = collect($permissions)->flatMap(fn (string $permission) => $this->usersWithPermission($permission))->unique('id')
            ->reject(fn (User $user) => $by !== null && $user->id === $by->id)->values();

        return $this->send($users, $notice);
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
