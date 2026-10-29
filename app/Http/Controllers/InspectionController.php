<?php

namespace App\Http\Controllers;

use App\Models\CastingLot;
use App\Models\Gauge;
use App\Models\InspectionPlan;
use App\Models\ProductionStageDefinition;
use App\Models\QualityDefect;
use App\Models\QualityInspection;
use App\Models\QualityInspectionResult;
use App\Services\Production\LotService;
use App\Services\Quality\CalibrationStatus;
use App\Services\Quality\QuickInspection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InspectionController extends Controller
{
    public const TYPES = ['FINAL', 'VISUAL', 'DIMENSIONAL', 'NDT', 'MECHANICAL', 'HARDNESS', 'METALLURGICAL', 'PRESSURE', 'IN_PROCESS'];

    public function index(Request $request): View
    {
        $rows = QualityInspection::with('lot.product')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('inspection_type', $request->query('type')))
            ->orderByDesc('created_at')->paginate(25)->withQueryString();

        $pending = app(\App\Services\Quality\PendingInspections::class);

        return view('inspections.index', ['inspections' => $rows, 'types' => self::TYPES, 'awaiting' => $pending->awaitingLots()->with('product')->orderBy('created_at')->get()]);
    }

    public function create(CastingLot $lot): View
    {
        $lot->load('product');

        return view('inspections.create', ['lot' => $lot, 'types' => self::TYPES, 'stages' => app(\App\Services\Production\ProcessRoutes::class)->stepsFor($lot->product_revision_id), 'plan' => $this->planFor($lot)]);
    }

    public function store(Request $request, CastingLot $lot, LotService $service, QuickInspection $quick): RedirectResponse
    {
        $d = $request->validate([
            'inspection_type' => ['required', Rule::in(self::TYPES)],
            'stage_definition_id' => ['nullable', 'uuid', Rule::exists('production_stage_definitions', 'id')],
            'sample_size' => ['required', 'integer', 'min:1'],
            'inspector_name' => ['nullable', 'string', 'max:150'],
        ]);
        $pieces = $service->pieces($lot);
        if ($d['sample_size'] > $pieces) {
            return back()->withErrors(['sample_size' => "Only {$pieces} piece(s) of the lot are left to inspect."])->withInput();
        }

        if ($lot->inspections()->where('inspection_type', $d['inspection_type'])->where('status', 'IN_PROGRESS')->exists()) {
            return back()->withErrors(['inspection_type' => 'A '.$d['inspection_type'].' inspection of this lot is already in progress; complete it first.'])->withInput();
        }
        if ($d['inspection_type'] === 'FINAL') {
            $cleared = (int) $lot->inspections()->where('inspection_type', 'FINAL')->where('status', 'COMPLETED')->sum(DB::raw('quantity_accepted + quantity_rework'));
            if ($lot->status === 'READY' || ($cleared > 0 && $cleared >= $pieces)) {
                return back()->withErrors(['inspection_type' => 'The final inspection of this lot is already complete for all its pieces.'])->withInput();
            }
        }

        $insp = DB::transaction(function () use ($d, $lot, $quick) {
            $insp = QualityInspection::create([
                'inspection_number' => $quick->nextInspectionNumber(),
                'casting_lot_id' => $lot->id, 'stage_definition_id' => $d['stage_definition_id'] ?? null,
                'inspection_type' => $d['inspection_type'], 'status' => 'IN_PROGRESS',
                'sample_size' => $d['sample_size'], 'quantity_inspected' => $d['sample_size'], 'quantity_pending' => $d['sample_size'],
                'inspector_name' => $d['inspector_name'] ?? null,
            ]);
            if ($d['inspection_type'] === 'FINAL' && ($plan = $this->planFor($lot))) {
                foreach ($plan->items as $i => $item) {
                    QualityInspectionResult::create([
                        'quality_inspection_id' => $insp->id, 'inspection_plan_item_id' => $item->id, 'sequence' => $i + 1,
                        'test_code' => substr($item->characteristic_code, 0, 50), 'test_name' => substr($item->characteristic_name, 0, 150),
                        'test_method' => $item->test_method_code ? substr($item->test_method_code, 0, 100) : null,
                        'result_type' => $item->data_type === 'NUMERIC' ? 'NUMERIC' : 'PASS_FAIL',
                        'minimum_value' => $item->minimum_value, 'maximum_value' => $item->maximum_value, 'unit' => $item->unit,
                    ]);
                }
            }

            return $insp;
        });

        return redirect()->route('inspections.show', $insp)->with('success', "Inspection {$insp->inspection_number} started".($insp->results()->count() ? ' with '.$insp->results()->count().' characteristic(s) from the approved plan.' : '.'));
    }

    public function show(QualityInspection $inspection): View
    {
        $inspection->load(['lot.product', 'results.defect', 'results.planItem', 'ncrs']);

        return view('inspections.show', [
            'inspection' => $inspection, 'defects' => QualityDefect::where('is_active', true)->orderBy('category')->orderBy('name')->get(),
            'gauges' => Gauge::where('status', 'IN_SERVICE')->orderBy('code')->get(),
        ]);
    }

    public function saveResults(Request $request, QualityInspection $inspection): RedirectResponse
    {
        if ($inspection->status !== 'IN_PROGRESS') {
            return back()->withErrors(['results' => 'This inspection is already completed.']);
        }
        $request->validate([
            'results' => ['required', 'array'],
            'results.*.measured_value' => ['nullable', 'numeric'],
            'results.*.gauge_id' => ['nullable', 'uuid', Rule::exists('gauges', 'id')],
            'results.*.text_result' => ['nullable', 'string', 'max:1000'],
            'results.*.passed' => ['nullable', 'in:1,0'],
            'results.*.quality_defect_id' => ['nullable', 'uuid', Rule::exists('quality_defects', 'id')],
            'results.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $warnings = [];
        $calibration = app(CalibrationStatus::class);
        foreach ($inspection->results as $r) {
            $in = $request->input("results.{$r->id}");
            if (! $in || blank($in['gauge_id'] ?? null) || blank($in['measured_value'] ?? null)) {
                continue;
            }
            $warning = $calibration->check(Gauge::find($in['gauge_id']), "results.{$r->id}.gauge_id");
            if ($warning) {
                $warnings[] = $r->test_name.' - '.$warning;
            }
        }
        foreach ($inspection->results as $r) {
            $in = $request->input("results.{$r->id}");
            if (! $in) {
                continue;
            }
            $measured = isset($in['measured_value']) && $in['measured_value'] !== '' ? (float) $in['measured_value'] : null;
            if ($r->result_type === 'NUMERIC') {
                $passed = $measured === null ? null : (($r->minimum_value === null || $measured >= $r->minimum_value) && ($r->maximum_value === null || $measured <= $r->maximum_value));
            } else {
                $passed = ($in['passed'] ?? '') === '' ? null : $in['passed'] === '1';
            }
            $r->update([
                'measured_value' => $measured, 'text_result' => $in['text_result'] ?? null, 'passed' => $passed,
                'quality_defect_id' => $passed === false ? ($in['quality_defect_id'] ?? null) : null, 'notes' => $in['notes'] ?? null,
                'tested_by' => $inspection->inspector_name, 'tested_at' => $passed === null ? null : now(),
                'gauge_id' => $r->result_type === 'NUMERIC' ? ($in['gauge_id'] ?: null) : null,
                'equipment_reference' => $r->result_type === 'NUMERIC' && ! empty($in['gauge_id']) ? Gauge::find($in['gauge_id'])?->code : $r->equipment_reference,
            ]);
        }

        return back()->with('success', 'Results saved.')->with($warnings ? ['warning' => implode(' ', $warnings)] : []);
    }

    public function addResult(Request $request, QualityInspection $inspection): RedirectResponse
    {
        if ($inspection->status !== 'IN_PROGRESS') {
            return back()->withErrors(['results' => 'This inspection is already completed.']);
        }
        $d = $request->validate([
            'test_name' => ['required', 'string', 'max:150'],
            'result_type' => ['required', 'in:NUMERIC,PASS_FAIL'],
            'minimum_value' => ['nullable', 'numeric'], 'maximum_value' => ['nullable', 'numeric', 'gte:minimum_value'],
            'unit' => ['nullable', 'string', 'max:30'],
            'test_method' => ['nullable', 'string', 'max:100'],
        ]);
        QualityInspectionResult::create($d + [
            'quality_inspection_id' => $inspection->id, 'sequence' => (int) $inspection->results()->max('sequence') + 1,
            'test_code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '-', $d['test_name']), 0, 50)),
        ]);

        return back()->with('success', 'Characteristic added.');
    }

    public function complete(Request $request, QualityInspection $inspection, LotService $service, QuickInspection $quick): RedirectResponse
    {
        if ($inspection->status !== 'IN_PROGRESS') {
            return back()->withErrors(['complete' => 'This inspection is already completed.']);
        }
        $d = $request->validate([
            'quantity_accepted' => ['required', 'integer', 'min:0'], 'quantity_rejected' => ['required', 'integer', 'min:0'],
            'quantity_rework' => ['required', 'integer', 'min:0'], 'quantity_pending' => ['required', 'integer', 'min:0'],
            'overall_disposition' => ['required', 'in:ACCEPT,REJECT,REWORK,PARTIAL'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'ncr_title' => ['nullable', 'string', 'max:200'],
        ]);
        foreach (['quantity_accepted', 'quantity_rejected', 'quantity_rework', 'quantity_pending'] as $field) {
            $d[$field] = (int) $d[$field];   // a form posts numbers as text
        }
        $inspection->load('results.planItem', 'lot');
        $sum = $d['quantity_accepted'] + $d['quantity_rejected'] + $d['quantity_rework'] + $d['quantity_pending'];
        if ($sum !== (int) $inspection->quantity_inspected) {
            return back()->withErrors(['complete' => "Accepted, rejected, rework and pending must add up to the {$inspection->quantity_inspected} piece(s) inspected (they add up to {$sum})."])->withInput();
        }
        $missing = $inspection->results->filter(fn ($r) => $r->passed === null && ($r->planItem?->is_mandatory ?? false) && ($r->planItem?->requires_recorded_result ?? true))->count();
        if ($missing > 0) {
            return back()->withErrors(['complete' => "{$missing} mandatory characteristic(s) have no result yet."])->withInput();
        }
        $failed = $inspection->results->where('passed', false);
        if ($failed->isNotEmpty() && $d['quantity_rejected'] + $d['quantity_rework'] === 0) {
            return back()->withErrors(['complete' => 'A characteristic failed, so not every piece can be accepted. Record rejected or rework pieces, or correct the result.'])->withInput();
        }
        if ($d['overall_disposition'] === 'ACCEPT' && ($d['quantity_rejected'] + $d['quantity_rework']) > 0) {
            return back()->withErrors(['complete' => 'The disposition is ACCEPT but there are rejected or rework pieces. Use PARTIAL.'])->withInput();
        }

        $ncr = null;
        DB::transaction(function () use ($inspection, $d, $failed, &$ncr, $service, $quick) {
            $inspection->update([
                'status' => 'COMPLETED', 'overall_disposition' => $d['overall_disposition'], 'quantity_accepted' => $d['quantity_accepted'],
                'quantity_rejected' => $d['quantity_rejected'], 'quantity_rework' => $d['quantity_rework'], 'quantity_pending' => $d['quantity_pending'],
                'inspected_at' => now(), 'remarks' => $d['remarks'] ?? null,
            ]);
            if ($d['quantity_rejected'] + $d['quantity_rework'] > 0 || $failed->isNotEmpty()) {
                $defect = $failed->first(fn ($r) => $r->quality_defect_id)?->defect;
                $ncr = $quick->raiseNcr(
                    $inspection, $inspection->lot, $defect, $d['quantity_rejected'] + $d['quantity_rework'], $d['quantity_rejected'] > 0, $inspection->inspector_name,
                    $failed->isNotEmpty() ? 'Failed: '.$failed->map(fn ($r) => $r->test_name.($r->measured_value !== null ? ' = '.$r->measured_value.($r->unit ? ' '.$r->unit : '') : ''))->implode('; ').'.' : (($d['remarks'] ?? null) ?: 'Pieces rejected or sent to rework at inspection.'),
                    ($d['ncr_title'] ?? null) ?: ($defect ? $defect->name : 'Failed inspection').' — '.$inspection->lot->lot_number,
                );
            }
            $service->refresh($inspection->lot);
            $lot = $inspection->lot->fresh();
            if ($inspection->inspection_type === 'FINAL' && $d['quantity_pending'] === 0 && $lot->status === 'IN_PRODUCTION' && ! $ncr && $lot->quantity_good > 0) {
                $lot->update(['status' => 'READY']);
            }
        });

        return redirect()->route('inspections.show', $inspection)->with('success', 'Inspection completed.'.($ncr ? " Nonconformance {$ncr->ncr_number} was raised." : ''));
    }

    private function planFor(CastingLot $lot): ?InspectionPlan
    {
        return InspectionPlan::with('items')->where('product_revision_id', $lot->product_revision_id)->where('status', 'APPROVED')
            ->where('plan_type', 'FINAL_INSPECTION')->whereNull('superseded_at')->orderByDesc('effective_at')->first();
    }
}
