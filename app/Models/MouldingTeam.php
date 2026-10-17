<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MouldingTeam extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function runs(): HasMany { return $this->hasMany(MouldingRun::class); }
    public function items(): HasMany { return $this->hasMany(MouldingWorkListItem::class); }
    public function employees(): BelongsToMany { return $this->belongsToMany(Employee::class, 'moulding_team_members', 'moulding_team_id', 'employee_id')->withTimestamps(); }
    public function leadEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'lead_employee_id'); }
}
