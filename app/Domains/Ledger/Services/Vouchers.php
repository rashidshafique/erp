<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Ledger\Models\LedgerAccount;
use App\Support\DocumentNumbers;
use App\Support\FinancialYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Voucher types and numbers (design 19b, 19c). JV, CPV, BPV, CRV and BRV are presets of one entry screen: a cash or bank type
 * names the cash or bank account and adds the balancing line itself. Numbers run per type from 10000, as JV-10000.
 * Documents post their journals under their own number.
 */
class Vouchers
{
    /** @var array<string, array{name: string, ref: string, counter: ?string, side: ?string, cash: bool}> */
    public const TYPES = [
        'JV' => ['name' => 'Journal', 'ref' => 'Reference', 'counter' => null, 'side' => null, 'cash' => false],
        'CPV' => ['name' => 'Cash payment', 'ref' => 'Paid to', 'counter' => 'Paid from', 'side' => 'out', 'cash' => true],
        'BPV' => ['name' => 'Bank payment', 'ref' => 'Cheque', 'counter' => 'Paid from', 'side' => 'out', 'cash' => false],
        'CRV' => ['name' => 'Cash receipt', 'ref' => 'Received from', 'counter' => 'Received into', 'side' => 'in', 'cash' => true],
        'BRV' => ['name' => 'Bank receipt', 'ref' => 'Received from', 'counter' => 'Received into', 'side' => 'in', 'cash' => false],
    ];

    /** "26" for the financial year that starts in 2026. */
    public static function yearCode(string|Carbon $date): string
    {
        return FinancialYear::containing(Carbon::parse($date))['start']->format('y');
    }

    /** "FY 2026-27", or "FY 2026" for a calendar year. */
    public static function yearLabel(string|Carbon $date): string
    {
        $y = FinancialYear::containing(Carbon::parse($date));

        return 'FY '.($y['start']->month === 1 ? $y['start']->year : $y['start']->year.'-'.$y['end']->format('y'));
    }

    /** "31 Oct 2026 · period Oct, FY 2026-27" */
    public static function periodLabel(string|Carbon $date): string
    {
        $d = Carbon::parse($date);

        return $d->format('d M Y').' · period '.$d->format('M').', '.self::yearLabel($d);
    }

    /** The next number of a type (JV-10000), counting posted journals and drafts. Call inside a transaction. */
    public static function next(string $type, string|Carbon|null $date = null): string
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['ledger-voucher-'.$type]);

        return DocumentNumbers::format($type, self::last($type) + 1);
    }

    /** What the next number would be, without holding it. */
    public static function preview(string $type, string|Carbon|null $date = null): string
    {
        return DocumentNumbers::format($type, self::last($type) + 1);
    }

    private static function last(string $type): int
    {
        return max(DocumentNumbers::last('ledger_journals', 'number', $type), DocumentNumbers::last('ledger_vouchers', 'number', $type));
    }

    /** Whether money through this account is cash (CPV, CRV) rather than bank (BPV, BRV). */
    public static function isCash(LedgerAccount $account): bool
    {
        return $account->id === LedgerSettings::account('cash')->id || str_contains(mb_strtolower($account->name), 'cash');
    }

    /**
     * Cash and bank accounts a cash or bank voucher can balance against: the active accounts in the sections of the cash and bank
     * accounts the posting rules use.
     *
     * @return Collection<int, LedgerAccount>
     */
    public static function counterAccounts(?bool $cash = null): Collection
    {
        $sections = collect([LedgerSettings::account('cash')->parent_id, LedgerSettings::account('bank')->parent_id])->filter()->unique();

        return LedgerAccount::postable()->whereIn('parent_id', $sections)->orderBy('code')->get()
            ->filter(fn ($a) => $cash === null || self::isCash($a) === $cash)->values();
    }

    /**
     * Accounts a person can type a voucher line to: active nominal accounts, without control accounts (receivables, payables, goods
     * received not invoiced), which only their documents post. The opening balance account stays, so its difference can be moved.
     *
     * @return Collection<int, LedgerAccount>
     */
    public static function lineAccounts(): Collection
    {
        $opening = LedgerSettings::account('opening_equity')->id;

        return LedgerAccount::postable()->where(fn ($q) => $q->whereNull('control')->orWhere('id', $opening))->orderBy('code')->get();
    }
}
