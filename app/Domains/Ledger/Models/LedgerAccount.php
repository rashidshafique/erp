<?php

namespace App\Domains\Ledger\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * An account in the chart (design 19a): a category (`22`), a section under it (`22-06`) or a nominal account in a section
 * (`22-06-01`). Categories and sections are groups that add up the accounts under them and take no postings of their own.
 * Codes are given in order and never change. A section carries the report line it shows on; an account can be offered in the
 * charge and deduction boxes of sales documents (`is_charge`), and a control account (`control`) is posted only by its documents.
 */
class LedgerAccount extends Model
{
    use HasUuids;

    protected $guarded = [];

    public const TYPES = ['ASSET' => 'Asset', 'LIABILITY' => 'Liability', 'EQUITY' => 'Equity', 'INCOME' => 'Income', 'EXPENSE' => 'Expense'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_charge' => 'boolean', 'sort_order' => 'integer'];
    }

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('code'); }
    public function lines(): HasMany { return $this->hasMany(JournalLine::class, 'account_id'); }

    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotExists(fn ($q) => $q->from('ledger_accounts as c')->whereColumn('c.parent_id', 'ledger_accounts.id'));
    }

    public function isGroup(): bool
    {
        return $this->children()->exists();
    }

    public function isCategory(): bool
    {
        return strlen($this->code) === 2;
    }

    public function isSection(): bool
    {
        return strlen($this->code) === 5;
    }

    public function hasPostings(): bool
    {
        return $this->lines()->exists();
    }

    /**
     * The next free code under a category (`22` gives `22-11`) or a section (`22-07` gives `22-07-06`), or null when all 99 are taken.
     * Codes already used are never given again while they exist.
     */
    public static function nextCode(string $parent): ?string
    {
        $last = self::where('code', 'like', $parent.'-__')->max('code');
        $n = ($last ? (int) substr($last, -2) : 0) + 1;

        return $n > 99 ? null : $parent.'-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    /** Assets and expenses grow with debits; liabilities, equity and income with credits. */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['ASSET', 'EXPENSE'], true);
    }

    public function label(): string
    {
        return $this->code.' '.$this->name;
    }

    /**
     * This account and every account under it.
     *
     * @return list<string>
     */
    public function treeIds(): array
    {
        $ids = [$this->id];
        $level = [$this->id];
        while ($level !== []) {
            $level = self::whereIn('parent_id', $level)->pluck('id')->all();
            $ids = array_merge($ids, $level);
        }

        return $ids;
    }

    /** Debits less credits on this account and its sub-accounts, between two dates (either may be open). */
    public function balance(?string $to = null, ?string $from = null): float
    {
        $row = JournalLine::whereIn('account_id', $this->treeIds())
            ->whereHas('journal', fn ($q) => $q->when($to, fn ($q) => $q->where('journal_date', '<=', $to))->when($from, fn ($q) => $q->where('journal_date', '>=', $from)))
            ->selectRaw('coalesce(sum(debit), 0) d, coalesce(sum(credit), 0) c')->first();

        return round((float) $row->d - (float) $row->c, 2);
    }

    /**
     * Every account in chart order, each with `depth`, `is_group` and `net` (debits less credits up to $to, groups adding up their children).
     *
     * @return Collection<int, LedgerAccount>
     */
    public static function tree(?string $to = null, ?string $from = null): Collection
    {
        $accounts = self::orderBy('sort_order')->orderBy('code')->get();
        $sums = JournalLine::join('ledger_journals', 'ledger_journals.id', '=', 'ledger_journal_lines.journal_id')
            ->when($to, fn ($q) => $q->where('journal_date', '<=', $to))->when($from, fn ($q) => $q->where('journal_date', '>=', $from))
            ->groupBy('account_id')->selectRaw('account_id, sum(debit) - sum(credit) net')->pluck('net', 'account_id');
        $children = $accounts->groupBy(fn ($a) => $a->parent_id ?? '');
        $out = collect();
        $walk = function (string $parent, int $depth) use (&$walk, $children, $sums, $out): float {
            $total = 0.0;
            foreach ($children->get($parent, collect()) as $account) {
                $out->push($account);
                $account->depth = $depth;
                $account->is_group = $children->has($account->id);
                $account->net = round((float) ($sums[$account->id] ?? 0) + ($account->is_group ? $walk($account->id, $depth + 1) : 0), 2);
                $total += $account->net;
            }

            return $total;
        };
        $walk('', 0);

        return $out;
    }
}
