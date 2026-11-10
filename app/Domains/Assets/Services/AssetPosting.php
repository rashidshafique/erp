<?php

namespace App\Domains\Assets\Services;

use App\Domains\Assets\Models\AssetEvent;
use App\Domains\Assets\Models\DepreciationRun;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Core\ModuleRegistry;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\Ledger;
use App\Domains\Ledger\Services\LedgerSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * What the register posts to the ledger:
 * - putting an asset in use: debit its class's cost account, credit capital work in progress (28e);
 * - a depreciation run: one journal on the last day of the month, debit each class's expense account, credit its accumulated
 *   depreciation account (28f);
 * - reversing a run: the exact opposite on the same date, refused while a later run is posted or the month is closed (28k).
 */
class AssetPosting
{
    /** The capital work in progress account of the starting chart. */
    public const CAPITAL_WIP = '21-04-01';

    public function __construct(private Ledger $ledger, private Depreciation $depreciation) {}

    public static function capitalWip(): LedgerAccount
    {
        return LedgerAccount::where('code', self::CAPITAL_WIP)->first()
            ?? throw new InvalidArgumentException('The chart has no capital work in progress account ('.self::CAPITAL_WIP.').');
    }

    /** Moves an asset in progress into use from the date given; its cost leaves capital work in progress for its class's cost account. */
    public function putInUse(FixedAsset $asset, string $date): FixedAsset
    {
        if ($asset->status !== 'IN_PROGRESS') {
            throw new InvalidArgumentException("{$asset->code} is already in use.");
        }

        return DB::transaction(function () use ($asset, $date) {
            $journal = null;
            if ($asset->cost > 0) {
                $this->needLedger();
                $journal = $this->ledger->post($date, [
                    ['debit' => $asset->cost, 'account_id' => $asset->assetClass->cost_account_id, 'description' => $asset->code],
                    ['credit' => $asset->cost, 'account_id' => self::capitalWip()->id, 'description' => $asset->code],
                ], ['type' => 'fixed_asset', 'id' => $asset->id], "{$asset->code} {$asset->name} put in use");
            }
            $asset->update(['status' => 'IN_USE', 'in_use_on' => $date]);
            AssetEvent::create(['fixed_asset_id' => $asset->id, 'kind' => 'IN_USE', 'happened_on' => $date, 'journal_id' => $journal?->id, 'user_id' => auth()->id(),
                'note' => $journal ? "Dr {$asset->assetClass->costAccount->code} / Cr ".self::CAPITAL_WIP.' Capital work in progress' : null]);

            return $asset;
        });
    }

    /** The posted run of a month, if there is one. */
    public function postedRun(Carbon $period): ?DepreciationRun
    {
        return DepreciationRun::where('period', $period->copy()->startOfMonth()->toDateString())->where('status', DepreciationRun::POSTED)->first();
    }

    /**
     * What a month's run would post: per class, the expense debited and the accumulated depreciation credited.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return list<array{account_id: string, debit?: float, credit?: float, description: string}>
     */
    public function journalLines(Collection $lines): array
    {
        $out = [];
        foreach ($lines->filter(fn ($l) => $l['charge'] > 0)->groupBy(fn ($l) => $l['asset']->asset_class_id) as $group) {
            $class = $group->first()['asset']->assetClass;
            if (! $class->expense_account_id || ! $class->depreciation_account_id) {
                throw new InvalidArgumentException("Asset class {$class->name} needs an expense and an accumulated depreciation account.");
            }
            $sum = round($group->sum('charge'), 2);
            $out[] = ['debit' => $sum, 'account_id' => $class->expense_account_id, 'description' => 'Depreciation · '.$class->name];
            $out[] = ['credit' => $sum, 'account_id' => $class->depreciation_account_id, 'description' => 'Depreciation · '.$class->name];
        }

        return $out;
    }

    /** Posts the month's run as one journal dated the last day of the month, keeping each asset's charge. */
    public function post(Carbon $period): DepreciationRun
    {
        $this->needLedger();
        $period = $period->copy()->startOfMonth();
        if ($this->postedRun($period)) {
            throw new InvalidArgumentException('The '.$period->format('F Y').' run is already posted.');
        }
        if (DepreciationRun::where('status', DepreciationRun::POSTED)->where('period', '>', $period->toDateString())->exists()) {
            throw new InvalidArgumentException('A later month is already posted; runs are posted in order.');
        }
        $lines = $this->depreciation->forMonth($period);
        if ($lines->sum('charge') <= 0) {
            throw new InvalidArgumentException('Nothing to depreciate in '.$period->format('F Y').'.');
        }

        return DB::transaction(function () use ($period, $lines) {
            $run = DepreciationRun::create(['period' => $period->toDateString(), 'status' => DepreciationRun::POSTED, 'total' => round($lines->sum('charge'), 2),
                'posted_at' => now(), 'posted_by_id' => auth()->id()]);
            $journal = $this->ledger->post($period->copy()->endOfMonth()->toDateString(), $this->journalLines($lines),
                ['type' => 'depreciation_run', 'id' => $run->id], 'Depreciation '.$period->format('F Y'));
            $run->update(['journal_id' => $journal->id]);
            foreach ($lines as $l) {
                $run->lines()->create(['fixed_asset_id' => $l['asset']->id, 'fills' => $l['fills'], 'rate' => $l['rate'], 'charge' => $l['charge'], 'book_before' => $l['book_before']]);
            }

            return $run;
        });
    }

    /**
     * Why a run cannot be reversed, or null when it can.
     *
     * @return array{message: string, later: DepreciationRun|null}|null
     */
    public function reverseBlock(DepreciationRun $run): ?array
    {
        if ($run->status !== DepreciationRun::POSTED) {
            return ['message' => 'This run is not posted.', 'later' => null];
        }
        $later = DepreciationRun::where('status', DepreciationRun::POSTED)->where('period', '>', $run->period->toDateString())->orderByDesc('period')->first();
        if ($later) {
            return ['message' => "The {$later->label()} run is posted. Runs are reversed newest first: reverse {$later->label()} first, then {$run->label()}.", 'later' => $later];
        }
        if (($closed = LedgerSettings::closedTo()) && $run->periodEnd()->toDateString() <= $closed) {
            return ['message' => $run->period->format('F').' is closed; reopen it in month close first.', 'later' => null];
        }

        return null;
    }

    /** Posts the exact opposite of the run's journal, dated like it, and marks the run reversed; the month can then be run again. */
    public function reverse(DepreciationRun $run, string $reason, ?string $detail = null): Journal
    {
        if ($block = $this->reverseBlock($run)) {
            throw new InvalidArgumentException($block['message']);
        }
        $this->needLedger();

        return DB::transaction(function () use ($run, $reason, $detail) {
            $reversal = $this->ledger->reverse($run->journal, $run->periodEnd()->toDateString(), "Reverses {$run->journal->number}: depreciation {$run->label()} · {$reason}");
            $run->update(['status' => DepreciationRun::REVERSED, 'reversal_journal_id' => $reversal->id, 'reversed_at' => now(), 'reversed_by_id' => auth()->id(),
                'reverse_reason' => $reason, 'reverse_detail' => $detail]);

            return $reversal;
        });
    }

    private function needLedger(): void
    {
        if (! app(ModuleRegistry::class)->isEnabled('ledger')) {
            throw new InvalidArgumentException('Switch on the General ledger under Settings > Modules to post fixed assets.');
        }
    }
}
