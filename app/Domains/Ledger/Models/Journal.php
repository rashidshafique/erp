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
    ];

    protected function casts(): array
    {
        return ['journal_date' => 'date', 'total' => 'float'];
    }

    public function lines(): HasMany { return $this->hasMany(JournalLine::class)->orderByDesc('debit')->orderBy('created_at'); }
    public function reverses(): BelongsTo { return $this->belongsTo(self::class, 'reverses_id'); }
    public function reversal(): HasOne { return $this->hasOne(self::class, 'reverses_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

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
