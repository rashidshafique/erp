<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions(): array
    {
        return \Illuminate\Support\Facades\DB::table('role_permissions')->where('role_id', $this->id)->pluck('permission')->all();
    }
}
