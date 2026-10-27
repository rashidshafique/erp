<?php

namespace App\Domains\Sales\Models;

use App\Models\Customer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A set of product prices valid between two dates. One list is the default for customers that have none of their own. */
class PriceList extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date', 'is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function items(): HasMany { return $this->hasMany(PriceListItem::class)->orderBy('product_id')->orderBy('basis')->orderBy('min_quantity'); }
    public function customers(): HasMany { return $this->hasMany(Customer::class); }

    /** Whether the list can price a document dated on this day. */
    public function appliesOn(CarbonInterface $day): bool
    {
        return $this->is_active && (! $this->valid_from || $this->valid_from->lte($day)) && (! $this->valid_to || $this->valid_to->gte($day));
    }
}
