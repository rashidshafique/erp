<?php

namespace App\Domains\Hr\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrPolicy extends Model
{
    use GeneratesCodes, HasUuids;

    protected $guarded = [];

    public string $codeKind = 'hr_policy';

    protected function casts(): array
    {
        return ['effective_on' => 'date', 'published_at' => 'datetime', 'requires_ack' => 'boolean'];
    }

    public function acks(): HasMany
    {
        return $this->hasMany(HrPolicyAck::class, 'policy_id');
    }
}
