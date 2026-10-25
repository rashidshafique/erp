<?php

namespace App\Domains\Tax\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tax at one rate from a date: charged (added to the invoice) or withheld (kept back by the buyer for the authority). A rate
 * change is a new row from its date, with the old one closed the day before, so earlier documents keep the rate they were made at.
 * The base is the line's value before tax (NET), that value with the charged tax (GROSS), or another tax's amount, by its code.
 */
class TaxComponent extends Model
{
    use HasUuids;

    public const KINDS = ['CHARGED' => 'Charged', 'WITHHELD' => 'Withheld'];

    protected $fillable = ['jurisdiction', 'tax_authority_id', 'code', 'name', 'kind', 'rate', 'base', 'effective_from', 'effective_to', 'note'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:3', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function authority(): BelongsTo
    {
        return $this->belongsTo(TaxAuthority::class, 'tax_authority_id');
    }

    /** The rows in force on a day. */
    public function scopeOn(Builder $query, CarbonInterface $day): Builder
    {
        $date = $day->toDateString();

        return $query->where('effective_from', '<=', $date)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }
}
