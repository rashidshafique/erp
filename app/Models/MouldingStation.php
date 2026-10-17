<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MouldingStation extends Model
{
    use HasUuids;

    public const TYPES = [
        'AUTOMATED_LINE' => 'Automated green sand line (one pattern at a time)',
        'MATCHPLATE_MACHINE' => 'Match plate machine',
        'FLOOR' => 'Floor / bench (loose patterns)',
        'JOLT_SQUEEZE' => 'Jolt-squeeze machine',
        'OTHER' => 'Other',
    ];

    protected $guarded = [];

    public function isAutomated(): bool
    {
        return $this->station_type === 'AUTOMATED_LINE';
    }

    /** Patterns this station can mould at once; an automated line is always one. */
    public function capacity(): int
    {
        return $this->isAutomated() ? 1 : max(1, (int) $this->max_patterns);
    }

    public function defaultTeam(): BelongsTo
    {
        return $this->belongsTo(MouldingTeam::class, 'default_moulding_team_id');
    }

    /** The sand this station moulds with: its own setting, else the first active sand system. */
    public function defaultSand(): ?string
    {
        return $this->default_sand_system ?: (SandSystem::names()[0] ?? null);
    }

    /** Items being moulded on this station right now, across every list. */
    public function runningCount(?string $exceptItemId = null): int
    {
        return $this->items()->where('status', 'IN_PROGRESS')->when($exceptItemId, fn ($q) => $q->where('id', '!=', $exceptItemId))->count();
    }

    public function runs(): HasMany
    {
        return $this->hasMany(MouldingRun::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MouldingWorkListItem::class);
    }
}
