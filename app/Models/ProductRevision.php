<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRevision extends Model
{
    use HasUuids;

    /** The name of a product's next revision: 0 for the first, then the next number; a product still on letters continues its letters. */
    public static function nextLabel(iterable $existing): string
    {
        $names = collect($existing)->map(fn ($r) => strtoupper(trim((string) $r)))->filter();
        if ($names->isEmpty()) {
            return '0';
        }
        if ($names->every(fn ($r) => ctype_digit($r))) {
            return (string) ($names->map(fn ($r) => (int) $r)->max() + 1);
        }
        if ($names->every(fn ($r) => preg_match('/^[A-Y]$/', $r))) {
            return chr(ord($names->max()) + 1);
        }
        for ($n = $names->count(); $names->contains((string) $n); $n++);

        return (string) $n;
    }

    protected $fillable = [
        'product_id',
        'revision',
        'status',
        'net_weight_kg',
        'engineering_notes',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'net_weight_kg' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();   // archived products still show on old records
    }

    public function patternCavities(): HasMany
    {
        return $this->hasMany(PatternRevisionCavity::class);
    }

    public function manufacturingMethods(): HasMany
    {
        return $this->hasMany(ManufacturingMethod::class);
    }
}
