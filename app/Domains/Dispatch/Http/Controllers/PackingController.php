<?php

namespace App\Domains\Dispatch\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CastingLot;
use App\Domains\Dispatch\Models\PackingRecord;
use App\Domains\Dispatch\Services\ShipmentService;
use App\Services\QualityGates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PackingController extends Controller
{
    public function index(): View
    {
        return view('dispatch::packing.index', ['records' => PackingRecord::withCount('items')->orderByDesc('created_at')->paginate(25)]);
    }

    public function create(ShipmentService $ship): View
    {
        $lots = CastingLot::with('product')->whereIn('status', ['READY', 'PACKED'])->orderBy('lot_number')->get()
            ->map(function ($l) use ($ship) { $l->packable = $ship->packable($l); $l->kg_each = $l->quantity_poured > 0 ? (float) $l->net_casting_weight_kg / $l->quantity_poured : 0; return $l; })->filter(fn ($l) => $l->packable > 0)->values();

        return view('dispatch::packing.create', ['lots' => $lots]);
    }

    public function store(Request $request, ShipmentService $ship): RedirectResponse
    {
        $d = $request->validate([
            'qty' => ['required', 'array'], 'qty.*' => ['nullable', 'integer', 'min:0'], 'kg' => ['nullable', 'array'], 'kg.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'packed_by' => ['nullable', 'string', 'max:100'], 'checked_by' => ['nullable', 'string', 'max:100'],
            'package_type' => ['nullable', 'string', 'max:60'], 'storage_location' => ['nullable', 'string', 'max:60'], 'package_count' => ['nullable', 'integer', 'min:1'],
            'packaging_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $pack = $ship->pack($d['qty'], $d, $d['kg'] ?? []);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['packing' => $e->getMessage()])->withInput();
        }

        return redirect()->route('packing.show', $pack)->with('success', "Packing record {$pack->packing_number} created.");
    }

    /** Lets one lot be packed although its heat's chemistry or thermal analysis check fails; the reason and who waived it stay on the lot. */
    public function waiveMaterialChecks(Request $request, CastingLot $lot, QualityGates $gates): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        if (! $gates->lotReadiness($lot)['waivable']) {
            return back()->withErrors(['waiver' => 'No chemistry or thermal analysis check is holding this lot, so there is nothing to waive.']);
        }
        $lot->update(['material_waived_at' => now(), 'material_waived_by' => $request->user()?->name, 'material_waiver_reason' => $d['reason']]);

        return back()->with('success', "Chemistry and thermal analysis checks waived for lot {$lot->label}.");
    }

    public function show(PackingRecord $packing, ShipmentService $ship): View
    {
        $packing->load('items.lot.product');

        return view('dispatch::packing.show', ['packing' => $packing, 'ship' => $ship]);
    }
}
