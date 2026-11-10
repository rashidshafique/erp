<?php

namespace App\Domains\Assets\Models;

use App\Domains\Ledger\Models\Journal;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line of an asset's history: added, put in use, capitalised, sold. */
class AssetEvent extends Model
{
    use HasUuids;

    public const KINDS = ['ADDED' => 'added', 'IN_USE' => 'in use', 'CAPITALISED' => 'capitalised', 'EDITED' => 'edited'];

    protected $table = 'fixed_asset_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['happened_on' => 'date'];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
