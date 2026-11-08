<?php

namespace App\Http\Controllers;

use App\Models\CastingLot;
use App\Models\NonconformanceReport;
use App\Models\QualityDefect;
use App\Services\Production\LotService;
use App\Services\Quality\QuickInspection;
use App\Services\Quality\ShipRule;
use App\Services\QualityGates;
use App\Support\QualityRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The quality tablet (design Q·2 to Q·5): lots through their steps with what blocks each, the lot and its blockers, the
 * inspection counted on the keypad, and the release or hold the customer's rule decides. The inspector never picks the path.
 */
class QualityTabletController extends Controller
{
    public function lots(QuickInspection $quick, QualityGates $gates, LotService $svc): View
    {
        $lots = CastingLot::with(['product', 'productionOrder'])->whereIn('status', ['IN_PRODUCTION', 'ON_HOLD', 'READY'])->orderBy('created_at')->get();
        $final = $lots->filter(fn (CastingLot $lot) => $lot->status !== 'READY' && $quick->toCheck($lot) > 0)->map(function (CastingLot $lot) use ($gates) {
            $lot->setAttribute('blocks', collect($gates->lotReadiness($lot)['checks'])->where('ok', false)->pluck('label')->all());

            return $lot;
        })->values();

        return view('floor.quality.lots', [
            'final' => $final,
            'steps' => ['In process' => $lots->filter(fn ($l) => $l->status === 'IN_PRODUCTION' && $quick->toCheck($l) === 0)->count(), 'Final inspection' => $final->count(),
                'On hold' => $lots->where('status', 'ON_HOLD')->count(), 'Ready to pack' => $lots->where('status', 'READY')->count()],
            'ncrs' => NonconformanceReport::whereNotIn('status', ['CLOSED', 'CANCELLED'])->count(),
        ]);
    }

    public function lot(CastingLot $lot, QualityGates $gates, QuickInspection $quick, ShipRule $rule): View
    {
        $lot->load(['product', 'productionOrder.salesOrderLine.salesOrder.customer']);

        return view('floor.quality.lot', ['lot' => $lot, 'ready' => $gates->lotReadiness($lot), 'rule' => $rule->forLot($lot), 'toCheck' => $quick->toCheck($lot),
            'heat' => $lot->sourceHeat(), 'accepted' => $rule->accepted($lot)]);
    }

    public function inspect(CastingLot $lot, QuickInspection $quick): View
    {
        $lot->load('product');

        return view('floor.quality.inspect', ['lot' => $lot, 'toCheck' => $quick->toCheck($lot), 'defects' => QualityDefect::where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    /** Q·5: what the counts mean for this lot under the customer's rule. Nothing is saved until the button is pressed. */
    public function review(Request $request, CastingLot $lot, QuickInspection $quick, ShipRule $rule): View|RedirectResponse
    {
        [$counts, $marks] = $this->counts($request, $lot, $quick);
        $bad = $counts['rejected'] + $counts['rework'];
        $total = $counts['accepted'] + $bad;
        $raises = QualityRules::raisesNcr($bad, $total);
        $r = $rule->forLot($lot);
        $path = $r['partial'] && $counts['accepted'] > 0 ? 'A' : 'B';
        $min = $rule->minimum($lot);

        return view('floor.quality.review', [
            'lot' => $lot->load(['product', 'productionOrder.salesOrderLine.salesOrder.customer']), 'counts' => $counts, 'marks' => $marks, 'total' => $total, 'raises' => $raises, 'rule' => $r, 'path' => $path, 'min' => $min,
            'percent' => $total > 0 ? round($bad / $total * 100, 1) : 0, 'threshold' => QualityRules::get('ncr_threshold'),
            'holds' => $path === 'B' && $raises, 'unreachable' => $min !== null && $counts['accepted'] + $counts['rework'] < $min,
        ]);
    }

    public function store(Request $request, CastingLot $lot, QuickInspection $quick): RedirectResponse
    {
        [$counts, $marks] = $this->counts($request, $lot, $quick);
        $rule = app(ShipRule::class);
        $hold = ! ($rule->forLot($lot)['partial'] && $counts['accepted'] > 0) && QualityRules::raisesNcr($counts['rejected'] + $counts['rework'], array_sum($counts));
        try {
            $res = $quick->record((string) $lot->product_revision_id, $counts['accepted'], $counts['rejected'], $counts['rework'], [], $request->user()?->name, $marks, $lot);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('floor.quality.lot.inspect', $lot)->withErrors(['inspection' => $e->getMessage()])->withInput();
        }
        $lot = $lot->fresh();
        if ($hold && $res['ncrs'] > 0) {
            $lot->update(['status' => 'ON_HOLD', 'quality_hold' => 'Waiting for rework · full lots only', 'notes' => trim($lot->notes."\n".now()->displayShortDateTime().' hold: waiting for rework, full lots only')]);
        }
        $ncr = $lot->ncrs()->latest('created_at')->first();

        return redirect()->route('floor.quality.lot', $lot)->with('success', $hold && $res['ncrs'] > 0
            ? "Lot held, waiting for rework. {$ncr?->ncr_number} raised."
            : ($res['ncrs'] > 0 ? "Released {$counts['accepted']} and raised {$ncr?->ncr_number} for the other ".($counts['rejected'] + $counts['rework']).' pieces.' : 'Inspection saved.'));
    }

    /**
     * @return array{0: array{accepted: int, rework: int, rejected: int}, 1: list<array{defect_id: string, quantity: int, disposition: string}>}
     */
    private function counts(Request $request, CastingLot $lot, QuickInspection $quick): array
    {
        $d = $request->validate([
            'accepted' => ['nullable', 'integer', 'min:0', 'max:999999'], 'rework' => ['nullable', 'integer', 'min:0', 'max:999999'], 'rejected' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'defects' => ['nullable', 'array'], 'defects.*.id' => ['nullable', 'uuid'], 'defects.*.qty' => ['nullable', 'integer', 'min:0'], 'defects.*.kind' => ['nullable', 'in:REJECT,REWORK'],
        ]);
        $counts = ['accepted' => (int) ($d['accepted'] ?? 0), 'rework' => (int) ($d['rework'] ?? 0), 'rejected' => (int) ($d['rejected'] ?? 0)];
        $total = array_sum($counts);
        if ($total <= 0) {
            throw ValidationException::withMessages(['inspection' => 'Count the pieces checked.']);
        }
        if ($total > $quick->toCheck($lot)) {
            throw ValidationException::withMessages(['inspection' => 'Only '.$quick->toCheck($lot).' piece(s) of this lot are left to check; '.$total.' were counted.']);
        }
        $marks = collect($d['defects'] ?? [])->filter(fn ($m) => ! empty($m['id']) && (int) ($m['qty'] ?? 0) > 0)
            ->map(fn ($m) => ['defect_id' => $m['id'], 'quantity' => (int) $m['qty'], 'disposition' => $m['kind'] ?? 'REJECT'])->values()->all();
        foreach (['REJECT' => 'rejected', 'REWORK' => 'rework'] as $kind => $key) {
            if (collect($marks)->where('disposition', $kind)->sum('quantity') > $counts[$key]) {
                throw ValidationException::withMessages(['inspection' => 'The defect lines add up to more than the pieces '.$key.'.']);
            }
        }

        return [$counts, $marks];
    }
}
