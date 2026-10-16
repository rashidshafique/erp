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
        $lots = CastingLot::with(['product', 'productionOrder', 'pourMouldAssembly.pour.heat'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), fn ($q) => $q->where('lot_number', 'ilike', '%'.$request->query('search').'%'))
            ->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('lots.index', ['lots' => $lots, 'counts' => CastingLot::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')]);
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
        $lot->update(['status' => 'ON_HOLD', 'notes' => trim($lot->notes."\n".now()->format('d M H:i').' hold: '.$d['reason'])]);

        return back()->with('success', 'Lot put on hold.');
    }

    public function release(CastingLot $lot): RedirectResponse
    {
        if ($lot->status !== 'ON_HOLD') {
            return back()->withErrors(['hold' => 'Only a lot on hold can be released.']);
        }
        if (! app(\App\Services\Approvals\ApprovalGate::class)->passes('lot.release_hold', 'CastingLot', (string) $lot->id, $lot->lot_number ?? (string) $lot->id, $msg)) {
            return back()->withErrors(['hold' => $msg]);
        }
        $lot->update(['status' => 'IN_PRODUCTION', 'notes' => trim($lot->notes."\n".now()->format('d M H:i').' released from hold')]);

        return back()->with('success', 'Lot released from hold.');
    }
}
