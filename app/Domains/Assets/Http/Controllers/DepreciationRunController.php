<?php

namespace App\Domains\Assets\Http\Controllers;

use App\Domains\Assets\Models\DepreciationRun;
use App\Domains\Assets\Services\AssetPosting;
use App\Domains\Assets\Services\Depreciation;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The monthly depreciation run (design 28f) and reversing it (28k). */
class DepreciationRunController extends Controller
{
    public function __construct(private Depreciation $depreciation, private AssetPosting $posting) {}

    /** The month asked for (`2026-11`), else the last month not yet posted up to this one. */
    private function period(Request $request): Carbon
    {
        $asked = (string) $request->input('period', '');
        if (preg_match('/^\d{4}-\d{2}$/', $asked) === 1) {
            return Carbon::createFromFormat('Y-m-d', $asked.'-01')->startOfDay();
        }
        $last = DepreciationRun::where('status', DepreciationRun::POSTED)->max('period');

        return $last ? Carbon::parse($last)->addMonthNoOverflow()->startOfMonth() : today()->startOfMonth();
    }

    public function show(Request $request): View
    {
        $period = $this->period($request);
        $posted = $this->posting->postedRun($period);
        $reversed = DepreciationRun::with(['journal', 'reversalJournal', 'postedBy', 'reversedBy'])->where('period', $period->toDateString())
            ->where('status', DepreciationRun::REVERSED)->latest('reversed_at')->first();
        if ($posted) {
            $posted->load(['lines.asset.assetClass', 'lines.asset.pattern', 'lines.asset.coreBox', 'lines.asset.equipment', 'journal', 'postedBy']);
            $lines = $posted->lines->map(fn ($l) => ['asset' => $l->asset, 'fills' => $l->fills, 'rate' => $l->rate, 'charge' => $l->charge, 'book_before' => $l->book_before, 'runs' => [], 'capped' => false]);
        } else {
            $lines = $this->depreciation->forMonth($period);
        }
        try {
            $journal = $this->posting->journalLines($lines);
            $problem = null;
        } catch (InvalidArgumentException $e) {
            [$journal, $problem] = [[], $e->getMessage()];
        }
        $accounts = LedgerAccount::whereIn('id', array_column($journal, 'account_id'))->get()->keyBy('id');
        $months = collect(range(0, 17))->map(fn ($i) => today()->startOfMonth()->subMonthsNoOverflow($i))->push($period)->unique(fn ($m) => $m->format('Y-m'))->sortDesc()->values();
        $skipped = \App\Domains\Assets\Models\FixedAsset::query()->where('status', 'IN_PROGRESS')->count();

        return view('assets::depreciation.show', [
            'period' => $period, 'posted' => $posted, 'reversed' => $reversed, 'months' => $months, 'problem' => $problem, 'skipped' => $skipped,
            'usage' => $lines->filter(fn ($l) => $l['asset']->method === 'USAGE')->values(),
            'time' => $lines->reject(fn ($l) => $l['asset']->method === 'USAGE')->values(),
            'total' => round($lines->sum('charge'), 2), 'capped' => $lines->where('capped', true)->count(),
            'noTaxClass' => $lines->filter(fn ($l) => ! $l['asset']->tax_class && ! $l['asset']->assetClass?->tax_class)->count(),
            'noFills' => $lines->filter(fn ($l) => $l['asset']->method === 'USAGE' && ! $l['fills'])->count(),
            'journal' => collect($journal)->map(fn ($l) => $l + ['account' => $accounts[$l['account_id']] ?? null])->sortBy(fn ($l) => isset($l['credit']) ? '1'.$l['account']?->code : '0'.$l['account']?->code)->values(),
            'block' => $posted ? $this->posting->reverseBlock($posted) : null,
            'later' => DepreciationRun::where('status', DepreciationRun::POSTED)->where('period', '>', $period->toDateString())->exists(),
        ]);
    }

    /** The month's charges, one asset a row, as a CSV to check or file. */
    public function sheet(Request $request): StreamedResponse
    {
        $period = $this->period($request);
        $posted = $this->posting->postedRun($period);
        $lines = $posted ? $posted->lines()->with('asset')->get()->map(fn ($l) => ['asset' => $l->asset, 'fills' => $l->fills, 'rate' => $l->rate, 'charge' => $l->charge, 'book_before' => $l->book_before])
            : $this->depreciation->forMonth($period);

        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Asset', 'Name', 'Method', 'Fills', 'Rate per fill', 'Book value before', 'Charge']);
            foreach ($lines as $l) {
                fputcsv($out, [$l['asset']->code, $l['asset']->name, $l['asset']->method, $l['fills'], $l['rate'], $l['book_before'], $l['charge']]);
            }
            fclose($out);
        }, 'depreciation-'.$period->format('Y-m').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function post(Request $request): RedirectResponse
    {
        $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $period = $this->period($request);
        try {
            $run = $this->posting->post($period);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['run' => $e->getMessage()]);
        }

        return redirect()->route('assets.depreciation', ['period' => $period->format('Y-m')])
            ->with('success', "Depreciation for {$run->label()} posted as {$run->journal->number}.");
    }

    public function run(DepreciationRun $run): RedirectResponse
    {
        return redirect()->route('assets.depreciation', ['period' => $run->period->format('Y-m')]);
    }

    public function reverse(Request $request, DepreciationRun $run): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:120'], 'detail' => ['nullable', 'string', 'max:300']]);
        try {
            $journal = $this->posting->reverse($run, $data['reason'], $data['detail'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['run' => $e->getMessage()]);
        }

        return redirect()->route('assets.depreciation', ['period' => $run->period->format('Y-m')])
            ->with('success', "{$run->journal->number} reversed by {$journal->number}. Run {$run->label()} again when the fills are right.");
    }
}
