<?php

namespace App\Domains\Enquiry\Models;

use App\Domains\Sales\Models\SalesOrder;
use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use GeneratesCodes, HasUuids;

    public string $codeKind = 'quotation';

    /** What a status is called on the list and the record (design 30). Issued is "Sent"; a rejected quotation is "Lost". */
    public const STATUSES = [
        'DRAFT' => 'Draft', 'AWAITING_APPROVAL' => 'Awaiting approval', 'ISSUED' => 'Sent', 'ACCEPTED' => 'Accepted', 'REJECTED' => 'Lost', 'EXPIRED' => 'Expired', 'SUPERSEDED' => 'Superseded',
    ];

    /** Why a revision is made (design 30c): the reason is picked first because it is what everyone asks six months later. */
    public const REVISION_REASONS = [
        'PRICE_ASK' => ['Customer asked for a lower price', 'The commonest one. Records the ask against the account.'],
        'VOLUME' => ['Volume changed', 'Different annual or batch quantity.'],
        'DRAWING' => ['Drawing revision', 'New rev from the customer; may need feasibility again.'],
        'COST' => ['Our cost changed', 'Metal, labour or a subcontract rate moved.'],
        'LEAD_TIME' => ['Lead time changed', 'Pattern or capacity moved the date.'],
        'CORRECTION' => ['Correct an error', 'Something was wrong on the last one.'],
    ];

    /** How a quotation closes (design 30e). A quote that ran out is "no reply", not "lost", so the win rate does not lie. */
    public const OUTCOMES = [
        'ACCEPTED' => ['Accepted', 'The customer has placed, or confirmed they will place, an order at this price.'],
        'LOST' => ['Lost', 'They went elsewhere, or the job is not happening.'],
        'NO_REPLY' => ['No reply', 'It ran out of validity and nobody came back. Keeps the history without calling it lost.'],
    ];

    /** Why it was lost (design 30e); the figures feed the lost-and-declined summary on the enquiries list. */
    public const LOST_REASONS = ['PRICE' => 'Price', 'LEAD_TIME' => 'Lead time', 'CAPABILITY' => 'Capability', 'NO_REPLY' => 'No reply', 'CANCELLED' => 'Job cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date', 'issued_at' => 'datetime', 'decided_at' => 'datetime', 'approval_asked_at' => 'datetime', 'approved_at' => 'datetime',
            'customer_po_date' => 'date', 'price_gap_pct' => 'float',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('line_no');
    }

    public function events(): HasMany
    {
        return $this->hasMany(QuotationEvent::class)->orderByDesc('happened_on')->orderByDesc('created_at');
    }

    /** Every revision of this quotation, newest first. They share the quotation's number and differ in version. */
    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'enquiry_id', 'enquiry_id')->where('code', $this->code)->orderByDesc('version');
    }

    /** The newest revision, the one the list shows. */
    public function isLatest(): bool
    {
        return ! self::where('enquiry_id', $this->enquiry_id)->where('code', $this->code)->where('version', '>', $this->version)->exists();
    }

    /** The status as the list and record show it: a sent quotation past its date reads Expired without waiting for the sweep. */
    public function statusKey(): string
    {
        return $this->status === 'ISSUED' && $this->isExpired() ? 'EXPIRED' : $this->status;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->statusKey()] ?? $this->status;
    }

    /** Pieces a year times the piece price, over the lines, falling back to the quoted quantity when the item has no annual figure. */
    public function annualValue(): float
    {
        return (float) $this->lines->sum(fn (QuotationLine $l) => ($l->annualQty() ?: $l->qty) * (float) $l->unit_price);
    }

    /**
     * The margin the floor is tested on: every part's margin weighted by its yearly value, so one thin part is carried by the others
     * (design 30g, the floor is tested on the set). A one-part quotation gives that part's margin. With no value to weigh by, the lowest.
     */
    public function setMargin(): float
    {
        $weights = $this->lines->map(fn (QuotationLine $l) => max(0.0, ($l->annualQty() ?: $l->qty) * (float) $l->unit_price));
        $total = $weights->sum();
        if ($total <= 0) {
            return (float) $this->lines->min('effective_margin_pct');
        }

        return (float) $this->lines->values()->reduce(fn ($sum, QuotationLine $l, $i) => $sum + (float) $l->effective_margin_pct * $weights[$i], 0.0) / $total;
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** Sent, still in date and the newest revision: the quotes a new sales order can start from (design 23a). */
    #[Scope]
    protected function awaitingAnswer(Builder $query): void
    {
        $query->where('status', 'ISSUED')->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('quotations as newer')->whereColumn('newer.enquiry_id', 'quotations.enquiry_id')
                ->whereColumn('newer.code', 'quotations.code')->whereColumn('newer.version', '>', 'quotations.version'));
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }

    /** One-off tooling price to invoice up front (zero when it is spread over the castings or not charged). */
    public function upfrontToolingPrice(): float
    {
        if ($this->tooling_mode !== 'UPFRONT') {
            return 0.0;
        }
        $markup = (float) \App\Domains\Enquiry\Services\Rates::get()['tooling_markup_pct'];

        return round($this->lines->sum('tooling_cost') * (1 + $markup / 100), 2);
    }
}
