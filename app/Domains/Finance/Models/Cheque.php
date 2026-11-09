<?php

namespace App\Domains\Finance\Models;

use App\Domains\Purchasing\Models\Supplier;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A post-dated cheque received from a customer or given to a supplier. HOLD: nothing counts until it clears, when the receipt or
 * payment is made on the clearing date. SETTLE: the receipt or payment is made when the cheque changes hands, through a holding
 * account, and clearing moves the money from there into the bank. A bounce undoes whichever was made.
 */
class Cheque extends Model
{
    use HasUuids;

    public const STATUSES = ['IN_HAND' => 'In hand', 'DEPOSITED' => 'Deposited', 'CLEARED' => 'Cleared', 'BOUNCED' => 'Bounced'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['handed_on' => 'date', 'cheque_date' => 'date', 'deposited_on' => 'date', 'cleared_on' => 'date', 'bounced_on' => 'date',
            'amount' => 'float', 'allocations' => 'array'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function receipt(): BelongsTo { return $this->belongsTo(Receipt::class); }
    public function payment(): BelongsTo { return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id'); }

    public function isReceived(): bool
    {
        return $this->direction === 'RECEIVED';
    }

    /** Still to clear or bounce. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['IN_HAND', 'DEPOSITED'], true);
    }

    /** @param  Builder<Cheque>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', ['IN_HAND', 'DEPOSITED']);
    }

    public function partyName(): ?string
    {
        return $this->isReceived() ? $this->customer?->name : $this->supplier?->name;
    }

    /** The receipt or supplier payment the cheque made, if any. */
    public function document(): Receipt|SupplierPayment|null
    {
        return $this->isReceived() ? $this->receipt : $this->payment;
    }
}
