<?php

namespace App\Http\Controllers;

use App\Models\CastingLot;
use App\Models\ProductionStageDefinition;
use App\Services\Production\LotService;
use App\Services\QualityGates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class LotController extends Controller
{
    public function index(Request $request): View
    {
        $lots = CastingLot::with(['product', 'productionOrder', 'pourMouldAssembly.pour.heat', 'stageEvents.stage'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w->where('code', 'ilike', '%'.$request->query('search').'%')->orWhere('lot_number', 'ilike', '%'.$request->query('search').'%')))
            ->orderByDesc('created_at')->paginate(25)->withQueryString();

        $firstIronHeld = CastingLot::where('status', 'ON_HOLD')->get()->filter(fn (CastingLot $lot) => $this->isFirstIronHold($lot))->count();

        return view('lots.index', ['firstIronHeld' => $firstIronHeld, 'lots' => $lots, 'counts' => CastingLot::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')]);
    }

    public function show(CastingLot $lot, QualityGates $gates, LotService $service): View
    {
        $lot->load(['product', 'productRevision', 'productionOrder', 'pourMouldAssembly.pour.heat.ironGrade', 'pourMouldAssembly.mouldAssembly',
            'stageEvents.stage', 'inspections', 'ncrs', 'packingItems.packing']);
        $heat = $lot->sourceHeat();

        return view('lots.show', [
            'lot' => $lot,
            'heat' => $heat,
            'readiness' => $gates->lotReadiness($lot),
            'stages' => ProductionStageDefinition::where('is_active', true)->orderBy('sequence')->get(),
            'pieces' => $service->pieces($lot),
            'cooling' => $service->cooling($lot),
            'thermal' => $heat?->thermalTests()->limit(2)->get() ?? collect(),
            'chemistry' => $heat?->chemistryReadings()->get()->last(),
            'packed' => (float) $lot->packingItems->sum('quantity_packed'),
        ]);
    }

    public function storeStage(Request $request, CastingLot $lot, LotService $service): RedirectResponse
    {
        $d = $request->validate([
            'stage_definition_id' => ['required', 'uuid', Rule::exists('production_stage_definitions', 'id')],
            'quantity_in' => ['required', 'integer', 'min:1'],
            'quantity_accepted' => ['required', 'integer', 'min:0'],
            'quantity_rejected' => ['required', 'integer', 'min:0'],
            'rejection_reason' => ['nullable', 'string', 'max:100'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'shift_code' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'cooling_override_reason' => ['nullable', 'string', 'max:300'],
        ]);
        try {
            $service->recordStage($lot, ProductionStageDefinition::findOrFail($d['stage_definition_id']), $d);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['stage' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Stage recorded.');
    }

    public function hold(Request $request, CastingLot $lot): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        if (in_array($lot->status, ['DISPATCHED', 'CLOSED', 'SCRAPPED'], true)) {
            return back()->withErrors(['hold' => "A {$lot->status} lot cannot be put on hold."]);
        }
        $lot->update(['status' => 'ON_HOLD', 'notes' => trim($lot->notes."\n".now()->displayShortDateTime().' hold: '.$d['reason'])]);

        return back()->with('success', 'Lot put on hold.');
    }

    public function release(CastingLot $lot): RedirectResponse
    {
        if (! $this->releaseHold($lot, $message)) {
            return back()->withErrors(['hold' => $message]);
        }

        return back()->with('success', 'Lot released from hold.');
    }

    /** Put the ticked lots on hold or release them, or do it for every lot matching the page's filter. */
    public function bulk(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'action' => ['required', Rule::in(['release', 'hold'])],
            'reason' => ['required_if:action,hold', 'nullable', 'string', 'max:500'],
            'lots' => ['required_without:all_matching', 'array'],
            'lots.*' => ['uuid', Rule::exists('casting_lots', 'id')],
            'all_matching' => ['nullable', 'boolean'],
        ]);
        $lots = $request->boolean('all_matching')
            ? CastingLot::query()->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w->where('code', 'ilike', '%'.$request->input('search').'%')->orWhere('lot_number', 'ilike', '%'.$request->input('search').'%')))->get()
            : CastingLot::whereIn('id', $d['lots'])->get();

        return $this->applyBulk($lots, $d['action'], $d['reason'] ?? '');
    }

    /**
     * Records the same stage(s) for many lots at once, e.g. fettling and shot blasting together. Every lot passes the chosen stages
     * in order with all its pieces; rejected pieces are taken from the lots oldest first. A lot that refuses (cooling, order of
     * stages) is skipped and listed.
     */
    public function bulkStage(Request $request, LotService $service): RedirectResponse
    {
        $d = $request->validate([
            'lots' => ['required', 'array'], 'lots.*' => ['uuid', Rule::exists('casting_lots', 'id')],
            'stages' => ['required', 'array'], 'stages.*' => ['uuid', Rule::exists('production_stage_definitions', 'id')],
            'rejected' => ['nullable', 'integer', 'min:0'],
            'rejection_reason' => ['nullable', 'string', 'max:100'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'cooling_override_reason' => ['nullable', 'string', 'max:300'],
        ]);
        if ((int) ($d['rejected'] ?? 0) > 0 && blank($d['rejection_reason'] ?? null)) {
            return back()->withErrors(['rejection_reason' => 'Give a rejection reason when pieces are rejected.'])->withInput();
        }
        $stages = ProductionStageDefinition::whereIn('id', $d['stages'])->orderBy('sequence')->get();
        $lots = CastingLot::whereIn('id', $d['lots'])->orderBy('created_at')->get();
        $rejectLeft = (int) ($d['rejected'] ?? 0);
        $done = 0;
        $skipped = [];

        foreach ($lots as $lot) {
            $reject = $rejectLeft;
            $recorded = 0;
            try {
                foreach ($stages as $stage) {
                    $in = $service->pieces($lot->fresh());
                    if ($in === 0) {
                        break;   // every piece was rejected: nothing is left to move on
                    }
                    $rej = $recorded === 0 ? min($reject, $in) : 0;
                    $service->recordStage($lot, $stage, [
                        'quantity_in' => $in, 'quantity_accepted' => $in - $rej, 'quantity_rejected' => $rej, 'rejection_reason' => $rej > 0 ? $d['rejection_reason'] : null,
                        'operator_name' => $d['operator_name'] ?? null, 'cooling_override_reason' => $d['cooling_override_reason'] ?? null,
                    ]);
                    $rejectLeft -= $rej;
                    $reject -= $rej;
                    $recorded++;
                }
                $done++;
            } catch (InvalidArgumentException $e) {
                $skipped[] = "{$lot->lot_number}: ".$e->getMessage();
            }
        }
        if ($rejectLeft > 0) {
            $skipped[] = "{$rejectLeft} rejected piece(s) could not be placed.";
        }

        return back()->with('success', "{$done} lot(s) moved through ".$stages->pluck('name')->implode(' and ').'.')
            ->with($skipped ? ['warning' => count($skipped).' problem(s): '.implode(' ', array_slice($skipped, 0, 3)).(count($skipped) > 3 ? ' ...' : '')] : []);
    }

    /** Release every lot on hold only because it was first iron. Containment and manual holds stay. */
    public function releaseFirstIron(): RedirectResponse
    {
        return $this->applyBulk(CastingLot::where('status', 'ON_HOLD')->get()->filter(fn (CastingLot $lot) => $this->isFirstIronHold($lot)), 'release', '');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CastingLot>  $lots
     */
    private function applyBulk($lots, string $action, string $reason): RedirectResponse
    {
        $done = 0;
        $skipped = [];
        foreach ($lots as $lot) {
            if ($action === 'release') {
                $ok = $this->releaseHold($lot, $message);
            } else {
                $ok = ! in_array($lot->status, ['DISPATCHED', 'CLOSED', 'SCRAPPED', 'ON_HOLD'], true);
                $message = "{$lot->lot_number} is {$lot->status}.";
                if ($ok) {
                    $lot->update(['status' => 'ON_HOLD', 'notes' => trim($lot->notes."\n".now()->displayShortDateTime().' hold: '.$reason)]);
                }
            }
            $ok ? $done++ : $skipped[] = $message;
        }
        $text = $done.' lot(s) '.($action === 'release' ? 'released from hold' : 'put on hold').'.';

        return back()->with('success', $text)->with($skipped ? ['warning' => count($skipped).' skipped: '.implode(' ', array_slice($skipped, 0, 3)).(count($skipped) > 3 ? ' ...' : '')] : []);
    }

    private function releaseHold(CastingLot $lot, ?string &$message = null): bool
    {
        if ($lot->status !== 'ON_HOLD') {
            $message = 'Only a lot on hold can be released.';

            return false;
        }
        if (! app(\App\Services\Approvals\ApprovalGate::class)->passes('lot.release_hold', 'CastingLot', (string) $lot->id, $lot->lot_number ?? (string) $lot->id, $msg)) {
            $message = $msg;

            return false;
        }
        $lot->update(['status' => 'IN_PRODUCTION', 'notes' => trim($lot->notes."\n".now()->displayShortDateTime().' released from hold')]);

        return true;
    }

    /** True when the latest hold written in the lot's notes is the first-iron one. */
    public function isFirstIronHold(CastingLot $lot): bool
    {
        $holds = collect(preg_split('/\R/', (string) $lot->notes))->filter(fn ($line) => str_contains($line, ' hold: '));

        return $holds->isNotEmpty() && str_contains($holds->last(), 'hold: first iron of heat');
    }
}
