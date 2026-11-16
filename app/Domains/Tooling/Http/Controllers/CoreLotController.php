<?php

namespace App\Domains\Tooling\Http\Controllers;

use App\Domains\Tooling\Services\CoreLotService;
use App\Domains\Tooling\Support\CoreRules;
use App\Http\Controllers\Controller;
use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\CoreLot;
use App\Models\MouldingRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Cores made, kept and used: a lot is one batch of one core from one box. */
class CoreLotController extends Controller implements HasMiddleware
{
    /** With core stock off in the core rules, none of these pages or actions exist. */
    public static function middleware(): array
    {
        return [new Middleware(function (Request $request, \Closure $next) {
            abort_unless(CoreRules::stockOn(), 404);

            return $next($request);
        })];
    }

    public function index(Request $request, CoreLotService $service): View
    {
        $all = $request->input('show') === 'all';
        $lots = CoreLot::with('coreDefinition', 'coreBox')->withSum('uses as taken', 'quantity')
            ->when($request->filled('core'), fn ($q) => $q->where('core_definition_id', $request->input('core')))
            ->when(! $all, fn ($q) => $q->where('status', '!=', 'USED_UP'))
            ->orderBy('made_on')->orderBy('lot_number')->paginate(50)->withQueryString();
        $qcOn = CoreRules::get('qc') === 'REQUIRED';

        return view('tooling::core-lots.index', [
            'lots' => $lots,
            'all' => $all,
            'onHand' => $service->onHand(),
            'stocked' => CoreDefinition::withTrashed()->whereIn('id', CoreLot::select('core_definition_id'))->orderBy('name')->get(['id', 'code', 'name']),
            'cores' => CoreDefinition::orderBy('name')->get(['id', 'code', 'name']),
            'openLots' => CoreLot::where('status', '!=', 'USED_UP')->count(),
            'anyLots' => CoreLot::exists(),
            'showCheck' => $qcOn || $lots->contains(fn (CoreLot $l) => $l->qc_status !== 'NOT_REQUIRED'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('tooling::core-lots.create', [
            'core' => CoreDefinition::find($request->input('core')),
            'run' => MouldingRun::find($request->input('run')),
            'cores' => CoreDefinition::where('status', 'ACTIVE')->orderBy('name')->get(),
            'boxes' => CoreBox::where('status', 'ACTIVE')->with('cavityMap')->orderBy('code')->get(),
            'runs' => MouldingRun::with('patternRevision.pattern')->latest('run_date')->limit(30)->get(),
            'nextLot' => \App\Support\DocumentNumbers::preview('core_lot'),
            'places' => CoreLot::whereNotNull('location')->distinct()->orderBy('location')->limit(50)->pluck('location'),
            'locationRequired' => CoreRules::get('location') === 'REQUIRED',
        ]);
    }

    /** Records a batch made. Ticking "used today" takes the cores used out to the run in the same step; the rest stay in the lot. */
    public function store(Request $request, CoreLotService $service): RedirectResponse
    {
        $data = $request->validate([
            'core_definition_id' => ['required', 'uuid', Rule::exists('core_definitions', 'id')->whereNull('deleted_at')],
            'core_box_id' => ['required', 'uuid', Rule::exists('core_boxes', 'id')->whereNull('deleted_at')],
            'quantity_made' => ['required', 'integer', 'min:1', 'max:100000'],
            'made_on' => ['required', 'date', 'before_or_equal:today'],
            'location' => ['nullable', 'string', 'max:100'],
            'use_now' => ['nullable', 'boolean'],
            'used_quantity' => ['nullable', 'integer', 'min:1', 'lte:quantity_made'],
            'moulding_run_id' => ['nullable', 'uuid', Rule::exists('moulding_runs', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['core_box_id.required' => 'Choose the core box the cores were made in.', 'used_quantity.lte' => 'More cannot be used than were made.']);
        $used = empty($data['use_now']) ? 0 : (int) ($data['used_quantity'] ?? $data['quantity_made']);
        $left = (int) $data['quantity_made'] - $used;

        $lot = DB::transaction(function () use ($data, $service, $used, $left) {
            $madeOn = Carbon::parse($data['made_on']);
            $lot = $service->make(CoreDefinition::findOrFail($data['core_definition_id']), CoreBox::findOrFail($data['core_box_id']), (int) $data['quantity_made'], $madeOn,
                $left > 0 ? ($data['location'] ?? null) : null, $data['notes'] ?? null, $left === 0);
            if ($used > 0) {
                $service->take($lot, $used, 'USED', $madeOn, $data['moulding_run_id'] ?? null);
            }

            return $lot;
        });

        return redirect()->route('core-lots.show', $lot)->with('success', "Lot {$lot->lot_number}: {$lot->quantity_made} made".($used > 0 ? ", {$used} used, {$left} left." : '.'));
    }

    public function show(CoreLot $coreLot, CoreLotService $service): View
    {
        $coreLot->load('coreDefinition', 'coreBox', 'uses.mouldingRun.patternRevision.pattern');

        return view('tooling::core-lots.show', [
            'lot' => $coreLot,
            'remaining' => $coreLot->remaining(),
            'runs' => MouldingRun::with('patternRevision.pattern')->latest('run_date')->limit(30)->get(),
            'places' => CoreLot::whereNotNull('location')->distinct()->orderBy('location')->limit(50)->pluck('location'),
        ]);
    }

    public function location(Request $request, CoreLot $coreLot, CoreLotService $service): RedirectResponse
    {
        $data = $request->validate(['location' => ['required', 'string', 'max:100']], ['location.required' => 'Say where the cores are kept.']);
        $service->store($coreLot, $data['location']);

        return back()->with('success', "Lot {$coreLot->lot_number} is at {$data['location']}.");
    }

    public function check(Request $request, CoreLot $coreLot, CoreLotService $service): RedirectResponse
    {
        $data = $request->validate(['result' => ['required', Rule::in(['PASSED', 'FAILED'])], 'qc_notes' => ['nullable', 'string', 'max:255']]);
        $service->check($coreLot, $data['result'] === 'PASSED', $data['qc_notes'] ?? null);

        return back()->with('success', "Lot {$coreLot->lot_number} ".($data['result'] === 'PASSED' ? 'passed.' : 'failed its check.'));
    }

    public function take(Request $request, CoreLot $coreLot, CoreLotService $service): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'kind' => ['required', Rule::in(['USED', 'SCRAPPED'])],
            'used_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$coreLot->made_on->format('Y-m-d')],
            'moulding_run_id' => ['nullable', 'uuid', Rule::exists('moulding_runs', 'id')],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['used_on.after_or_equal' => 'The cores cannot be used before they were made.']);
        $service->take($coreLot, (int) $data['quantity'], $data['kind'], Carbon::parse($data['used_on']), $data['moulding_run_id'] ?? null, $data['notes'] ?? null);

        return back()->with('success', "{$data['quantity']} ".($data['kind'] === 'USED' ? 'used' : 'scrapped').' from lot '.$coreLot->lot_number.'.');
    }

    /** Issues the cores a moulding run needs from stock, oldest lots first. */
    public function issueToRun(Request $request, MouldingRun $mouldingRun, CoreLotService $service): RedirectResponse
    {
        $data = $request->validate([
            'core_definition_id' => ['required', 'uuid', Rule::exists('core_definitions', 'id')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        $core = CoreDefinition::withTrashed()->findOrFail($data['core_definition_id']);
        $service->issueToRun($core, (int) $data['quantity'], $mouldingRun);

        return back()->with('success', "{$data['quantity']} {$core->name} issued to {$mouldingRun->run_number}.");
    }
}
