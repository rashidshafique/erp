<?php

namespace App\Domains\Finance\Models;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sales invoice or a credit note. It can be raised from a dispatch, from a sales order, or on its own with free lines
 * (`source_type` says which). A credit note names the invoice it reduces.
 */
class Invoice extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'issued_at' => 'datetime', 'voided_at' => 'datetime',
            'subtotal' => 'float', 'tax_total' => 'float', 'total' => 'float', 'tax_withheld' => 'float'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function lines(): HasMany { return $this->hasMany(InvoiceLine::class)->orderBy('line_number'); }
    public function allocations(): HasMany { return $this->hasMany(ReceiptAllocation::class); }
    public function certificateAllocations(): HasMany { return $this->hasMany(WithholdingCertificateAllocation::class); }
    public function original(): BelongsTo { return $this->belongsTo(self::class, 'original_invoice_id'); }
    public function creditNotes(): HasMany { return $this->hasMany(self::class, 'original_invoice_id')->where('kind', 'CREDIT_NOTE'); }

    /**
     * The invoice's taxes added up across lines, one row per tax and rate, charged ones first. Lines saved before taxes were
     * broken down count as one tax at their percent.
     *
     * @return \Illuminate\Support\Collection<int, array{code: string, label: string, authority: ?string, rate: float, base: float, amount: float, withheld: bool}>
     */
    public function taxBreakdown(): \Illuminate\Support\Collection
    {
        return $this->lines->flatMap(fn (InvoiceLine $l) => $l->taxes ?? [['code' => 'TAX', 'label' => 'Tax', 'authority' => null, 'rate' => $l->tax_percent, 'base' => $l->line_net, 'amount' => $l->line_tax, 'withheld' => false]])
            ->filter(fn ($t) => (float) $t['amount'] != 0.0)
            ->groupBy(fn ($t) => $t['code'].'|'.$t['rate'])
            ->map(fn ($group) => ['code' => $group->first()['code'], 'label' => $group->first()['label'], 'authority' => $group->first()['authority'] ?? null, 'rate' => (float) $group->first()['rate'],
                'base' => round($group->sum('base'), 2), 'amount' => round($group->sum('amount'), 2), 'withheld' => (bool) $group->first()['withheld']])
            ->sortBy(fn ($t) => $t['withheld'])->values();
    }

    /** What the customer pays the company: the total less the tax they withhold for the authority. */
    public function payableToUs(): float
    {
        return round($this->total - (float) $this->tax_withheld, 2);
    }

    public function isCreditNote(): bool
    {
        return $this->kind === 'CREDIT_NOTE';
    }

    public function paid(): float
    {
        return round((float) $this->allocations()->sum('amount'), 2);
    }

    /** What issued credit notes have taken off this invoice. */
    public function credited(): float
    {
        return round((float) $this->creditNotes()->where('status', 'ISSUED')->sum('total'), 2);
    }

    /** Withheld tax that customer certificates have settled, for one tax code or all of them. */
    public function withheldSettled(?string $taxCode = null): float
    {
        return round((float) WithholdingCertificateAllocation::where('invoice_id', $this->id)
            ->whereHas('certificate', fn ($q) => $q->where('status', 'ACTIVE')->when($taxCode, fn ($q) => $q->where('tax_code', $taxCode)))->sum('amount'), 2);
    }

    /** Tax the customer withheld that no certificate has settled yet: for one tax code, or the whole, never more than is owed. */
    public function withheldOpen(?string $taxCode = null): float
    {
        $withheld = $taxCode === null ? (float) $this->tax_withheld
            : (float) $this->taxBreakdown()->filter(fn ($t) => $t['withheld'] && $t['code'] === $taxCode)->sum('amount');

        return round(max(0.0, min($withheld - $this->withheldSettled($taxCode), $this->balance())), 2);
    }

    /** What the customer still has to pay in money: the balance less the withheld tax they will cover with a certificate. */
    public function cashDue(): float
    {
        return round($this->balance() - $this->withheldOpen(), 2);
    }

    /** Still owed on an issued invoice. */
    public function balance(): float
    {
        return $this->status === 'ISSUED' && ! $this->isCreditNote() ? round($this->total - $this->paid() - $this->credited() - $this->withheldSettled(), 2) : 0.0;
    }

    public function isOverdue(): bool
    {
        return $this->balance() > 0 && $this->due_date !== null && $this->due_date->lt(today());
    }
}
