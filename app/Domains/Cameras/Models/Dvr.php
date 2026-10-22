<?php

namespace App\Domains\Cameras\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A recorder that serves its cameras over HTTP. The password is stored encrypted and is never sent to the browser. */
class Dvr extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'brand', 'protocol', 'host', 'port', 'username', 'password', 'active', 'notes'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'encrypted', 'active' => 'boolean', 'port' => 'integer'];
    }

    public function cameras(): HasMany
    {
        return $this->hasMany(Camera::class)->orderBy('sort_order')->orderBy('channel');
    }

    /** The recorder's web address, such as http://192.168.1.108:80. */
    public function baseUrl(): string
    {
        return $this->protocol.'://'.$this->host.':'.$this->port;
    }
}
