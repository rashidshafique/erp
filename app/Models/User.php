<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_active', 'must_change_password', 'profile_image_path', 'use_gravatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'use_gravatar' => 'boolean',
        ];
    }

    private ?array $permissionCache = null;

    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function isAdmin(): bool
    {
        return $this->roles->contains('code', 'ADMIN');
    }

    /** @return array<int,string> */
    public function permissions(): array
    {
        return $this->permissionCache ??= \Illuminate\Support\Facades\DB::table('role_permissions')
            ->whereIn('role_id', $this->roles()->pluck('roles.id'))->pluck('permission')->unique()->values()->all();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && ($this->isAdmin() || in_array($permission, $this->permissions(), true));
    }

    /**
     * The picture to show for this user: an uploaded image first, then Gravatar when switched on, otherwise none (initials are shown).
     */
    public function avatarUrl(): ?string
    {
        if ($this->profile_image_path) {
            return route('account.avatar', ['user' => $this->id, 'v' => md5($this->profile_image_path)]);
        }

        return $this->use_gravatar
            ? 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($this->email))).'?s=96&d=404'
            : null;
    }
}
