<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A batch of one core made on one day from one core box: made, then kept on a shelf or used, the same day or later. */
class CoreLot extends Model
{
    use HasUuids;

    public const STATUSES = ['MADE' => 'Made', 'STORED' => 'Stored', 'USED_UP' => 'Used up'];

    public const QC = ['NOT_REQUIRED' => 'Not required', 'PENDING' => 'To check', 'PASSED' => 'Passed', 'FAILED' => 'Failed'];

    protected $fillable = ['lot_number', 'core_definition_id', 'core_box_id', 'quantity_made', 'made_on', 'location', 'status', 'qc_status', 'qc_notes', 'notes'];

    protected $casts = ['quantity_made' => 'integer', 'made_on' => 'date'];

    public function coreDefinition(): BelongsTo
    {
        return $this->belongsTo(CoreDefinition::class)->withTrashed();
    }

    public function coreBox(): BelongsTo
    {
        return $this->belongsTo(CoreBox::class)->withTrashed();
    }

    public function uses(): HasMany
    {
        return $this->hasMany(CoreLotUse::class)->orderBy('used_on')->orderBy('created_at');
    }

    /** Cores still on hand: made less everything used or scrapped. */
    public function remaining(): int
    {
        return max(0, $this->quantity_made - (int) $this->uses()->sum('quantity'));
    }

    /** Whether the lot may be issued: a core waiting for or failing its check stays on the shelf. */
    public function isIssuable(): bool
    {
        return in_array($this->qc_status, ['NOT_REQUIRED', 'PASSED'], true);
    }
}
