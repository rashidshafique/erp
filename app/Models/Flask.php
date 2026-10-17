<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One physical flask: a box of a flask size, used as the drag, the cope, a cheek or a stack section of a mould. It is
 * available, in a mould, in repair, retired or lost; its use count grows each time a mould made in it is knocked out.
 */
class Flask extends Model
{
    use Concerns\GeneratesCodes, HasUuids, SoftDeletes;

    public string $codeKind = 'flask';

    public const POSITIONS = ['DRAG' => 'Drag (bottom)', 'COPE' => 'Cope (top)', 'CHEEK' => 'Cheek (middle)', 'STACK' => 'Stack section', 'ANY' => 'Either drag or cope'];

    public const CONSTRUCTIONS = ['TIGHT' => 'Tight flask', 'SNAP' => 'Snap flask (removed before pouring)', 'SLIP_JACKET' => 'Slip / jacket', 'OTHER' => 'Other'];

    public const STATUSES = ['AVAILABLE' => 'Available', 'IN_MOULD' => 'In a mould', 'REPAIR' => 'In repair', 'RETIRED' => 'Retired', 'LOST' => 'Lost'];

    protected $guarded = [];

    protected $casts = ['weight_kg' => 'decimal:2', 'cycles' => 'integer', 'last_used_at' => 'datetime'];

    public function size(): BelongsTo
    {
        return $this->belongsTo(FlaskSize::class, 'flask_size_id')->withTrashed();
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Flask::class, 'partner_flask_id');
    }

    public function currentMould(): BelongsTo
    {
        return $this->belongsTo(MouldAssembly::class, 'current_mould_assembly_id');
    }

    public function currentRun(): BelongsTo
    {
        return $this->belongsTo(MouldingRun::class, 'current_moulding_run_id');
    }

    /** The mould layers this flask has been part of, newest first. */
    public function layers(): HasMany
    {
        return $this->hasMany(MouldLayer::class)->latest('created_at');
    }

    /** Whether the flask can go into the given layer of a mould: the bottom, the top, or one in between. */
    public function fits(string $layerType): bool
    {
        return match ($layerType) {
            'DRAG' => in_array($this->position, ['DRAG', 'ANY'], true),
            'COPE' => in_array($this->position, ['COPE', 'ANY'], true),
            default => in_array($this->position, ['STACK', 'CHEEK', 'ANY'], true),
        };
    }

    public function label(): string
    {
        return $this->code.($this->mark_code && $this->mark_code !== $this->code ? ' · '.$this->mark_code : '');
    }
}
