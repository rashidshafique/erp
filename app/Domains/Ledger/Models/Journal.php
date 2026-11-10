<?php

namespace App\Domains\Ledger\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One double entry: lines whose debits equal their credits. A journal is posted by a Finance document (`source_type`), by hand, or
 * reverses another; it is never edited, only reversed.
 */
class Journal extends Model
{
    use HasUuids;

    protected $table = 'ledger_journals';

    protected $guarded = [];

    /** @var array<string, array{0: string, 1: ?string}> Source type => [label, route of the document page]. */
    public const SOURCES = [
        'invoice' => ['Invoice', 'invoices.show'],
        'supplier_bill' => ['Supplier bill', 'bills.show'],
        'receipt' => ['Receipt', 'receipts.show'],
        'supplier_payment' => ['Supplier payment', 'supplier-payments.show'],
        'journal' => ['Journal', 'ledger.journals.show'],
        'withholding_certificate' => ['Tax certificate', null],
        'cheque' => ['Cheque', 'cheques.show'],
        'depreciation_run' => ['Depreciation run', 'assets.runs.show'],
        'fixed_asset' => ['Fixed asset', 'assets.show'],
        'receipt_allocation' => ['Receipt', null],
        'supplier_payment_allocation' => ['Supplier payment', null],
    ];

    protected function casts(): array
    {
        return ['journal_date' => 'date', 'total' => 'float'];
    }

    public function lines(): HasMany { return $this->hasMany(JournalLine::class)->orderByDesc('debit')->orderBy('created_at'); }
    public function reverses(): BelongsTo { return $this->belongsTo(self::class, 'reverses_id'); }
    public function reversal(): HasOne { return $this->hasOne(self::class, 'reverses_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function counterAccount(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'counter_account_id'); }

    /** The memo without the "Invoice I-10000 · " a document's journal starts with. */
    public function narration(): string
    {
        $memo = (string) $this->memo;

        return $this->source_type && str_contains($memo, ' · ') ? trim(substr($memo, strpos($memo, ' · ') + 3)) : $memo;
    }

    /** What posted it, for a statement: the document kind, or the voucher type's name for one entered by hand. */
    public function sourceKind(): string
    {
        return match ($this->source_type) {
            'invoice', 'supplier_bill' => $this->typeLabel(),
            null => \App\Domains\Ledger\Services\Vouchers::TYPES[$this->voucher_type]['name'] ?? 'Journal',
            default => $this->sourceLabel(),
        };
    }

    /** JV, CPV, BPV, CRV or BRV for a voucher; the document's kind for one posted under a document's number. */
    public function typeLabel(): string
    {
        if ($this->voucher_type) {
            return $this->voucher_type;
        }

        return match ($this->source_type) {
            'invoice' => str_starts_with((string) $this->memo, 'Credit note') ? 'Credit note' : 'Sales invoice',
            'supplier_bill' => str_starts_with((string) $this->memo, 'Supplier credit note') ? 'Supplier credit' : 'Purchase invoice',
            null => 'JV',
            default => $this->sourceLabel(),
        };
    }

    public function sourceLabel(): string
    {
        return $this->source_type ? (self::SOURCES[$this->source_type][0] ?? $this->source_type) : 'Manual';
    }

    public function sourceUrl(): ?string
    {
        $route = self::SOURCES[$this->source_type][1] ?? null;

        return $route && $this->source_id && \Illuminate\Support\Facades\Route::has($route) ? route($route, $this->source_id) : null;
    }
}
