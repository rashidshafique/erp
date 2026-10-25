<?php

namespace App\Domains\Tax\Models;

use App\Domains\Parties\Models\Party;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer's or supplier's tax status, region, whether it withholds tax, and its registration numbers keyed as the country pack names them. */
class PartyTaxProfile extends Model
{
    use HasUuids;

    protected $fillable = ['party_id', 'tax_status', 'region', 'is_withholding_agent', 'registrations'];

    protected function casts(): array
    {
        return ['is_withholding_agent' => 'boolean', 'registrations' => 'array'];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
