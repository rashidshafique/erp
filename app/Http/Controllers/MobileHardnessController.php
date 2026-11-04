<?php

namespace App\Http\Controllers;

use App\Models\CastingLot;
use App\Models\InspectionPlan;
use App\Models\QualityInspection;
use App\Models\QualityInspectionResult;
use App\Services\Quality\QuickInspection;
use App\Services\Quality\TestAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Phone hardness entry (design 26b): readings on a lot against its spec; an out-of-spec reading offers to hold the lot. */
class MobileHardnessController extends Controller
{
    public const READINGS = 5;

    public function create(CastingLot $lot): View
    {
        $lot->load('product');

        return view('m.hardness', ['lot' => $lot, 'spec' => $this->spec($lot), 'count' => self::READINGS]);
    }

    public function store(Request $request, CastingLot $lot, QuickInspection $quick, TestAlerts $alerts): RedirectResponse
    {
        $request->validate(['readings' => ['required', 'array', 'max:20'], 'readings.*' => ['nullable', 'numeric', 'between:1,2000'], 'hold' => ['nullable', 'boolean'], 'unit' => ['nullable', 'string', 'max:30']]);
        $readings = collect($request->input('readings'))->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (float) $v)->values();
        if ($readings->isEmpty()) {
            return back()->withErrors(['readings' => 'Enter at least one reading.'])->withInput();
        }
        $spec = $this->spec($lot);
        $in = fn (float $v): bool => ($spec['min'] === null || $v >= $spec['min']) && ($spec['max'] === null || $v <= $spec['max']);
        $bad = $spec['min'] === null && $spec['max'] === null ? 0 : $readings->reject($in)->count();
        $by = $request->user()?->name;

        $inspection = DB::transaction(function () use ($lot, $readings, $spec, $in, $bad, $by, $quick, $request) {
            $inspection = QualityInspection::create([
                'inspection_number' => $quick->nextInspectionNumber(), 'casting_lot_id' => $lot->id, 'inspection_type' => 'HARDNESS', 'status' => 'COMPLETED',
                'overall_disposition' => $bad ? 'PARTIAL' : 'ACCEPT', 'sample_size' => $readings->count(), 'quantity_inspected' => $readings->count(),
                'quantity_accepted' => $readings->count() - $bad, 'quantity_rejected' => 0, 'quantity_rework' => 0, 'quantity_pending' => $bad,
                'inspector_name' => $by, 'inspected_at' => now(), 'remarks' => 'Hardness readings entered on the phone',
            ]);
            foreach ($readings as $i => $v) {
                $judged = $spec['min'] === null && $spec['max'] === null ? null : $in($v);
                QualityInspectionResult::create([
                    'quality_inspection_id' => $inspection->id, 'sequence' => $i + 1, 'test_code' => 'HARDNESS', 'test_name' => 'Hardness '.($i + 1), 'result_type' => 'NUMERIC',
                    'minimum_value' => $spec['min'], 'maximum_value' => $spec['max'], 'unit' => $spec['unit'] ?? $request->input('unit'),
                    'measured_value' => $v, 'passed' => $judged, 'tested_by' => $by, 'tested_at' => now(),
                ]);
            }
            if ($bad && $request->boolean('hold') && ! in_array($lot->status, ['DISPATCHED', 'CLOSED', 'SCRAPPED'], true)) {
                $lot->update(['status' => 'ON_HOLD', 'notes' => trim($lot->notes."\n".now()->displayShortDateTime().' hold: hardness out of spec ('.$inspection->inspection_number.')')]);
            }

            return $inspection;
        });
        $alerts->inspection($inspection, $request->user());

        return redirect()->route('m.home')->with('success', $bad ? "{$bad} reading(s) out of spec." : 'Readings saved.');
    }

    /** @return array{min: ?float, max: ?float, unit: ?string, label: ?string} the hardness limits of the lot's approved final inspection plan, when it has one */
    private function spec(CastingLot $lot): array
    {
        $plan = InspectionPlan::with('items')->where('product_revision_id', $lot->product_revision_id)->where('status', 'APPROVED')
            ->where('plan_type', 'FINAL_INSPECTION')->whereNull('superseded_at')->orderByDesc('effective_at')->first();
        $item = $plan?->items->first(fn ($i) => preg_match('/hard|hb|hrc|brinell/i', $i->characteristic_code.' '.$i->characteristic_name));

        return ['min' => $item?->minimum_value !== null ? (float) $item->minimum_value : null, 'max' => $item?->maximum_value !== null ? (float) $item->maximum_value : null,
            'unit' => $item?->unit, 'label' => $item?->characteristic_name];
    }
}
