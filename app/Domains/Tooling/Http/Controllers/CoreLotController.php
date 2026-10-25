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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Cores made, kept and used: a lot is one batch of one core from one box. */
class CoreLotController extends Controller
{
    public function index(Request $request, CoreLotService $service): View
    {
        $lots = CoreLot::with('coreDefinition', 'coreBox')->withSum('uses as taken', 'quantity')
            ->when($request->filled('core'), fn ($q) => $q->where('core_definition_id', $request->input('core')))
            ->when($request->input('show', 'open') === 'open', fn ($q) => $q->where('status', '!=', 'USED_UP'))
            ->latest('made_on')->latest('created_at')->paginate(50)->withQueryString();

        return view('tooling::core-lots.index', [
            'lots' => $lots,
            'onHand' => $service->onHand(),
            'cores' => CoreDefinition::orderBy('code')->get(['id', 'code', 'name']),
            'rules' => collect(CoreRules::RULES)->map(fn ($r, $k) => CoreRules::get($k))->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $core = CoreDefinition::find($request->input('core'));

        return view('tooling::core-lots.create', [
            'core' => $core,
            'cores' => CoreDefinition::where('status', 'ACTIVE')->orderBy('code')->get(),
            'boxes' => CoreBox::where('status', 'ACTIVE')->with('cavityMap')->orderBy('code')->get(),
            'runs' => MouldingRun::latest('run_date')->limit(30)->get(),
            'locationRequired' => CoreRules::get('location') === 'REQUIRED',
        ]);
    }

    /** Records a batch made; "used straight away" takes the whole batch out again in the same step. */
    public function store(Request $request, CoreLotService $service): RedirectResponse
    {
        $data = $request->validate([
            'core_definition_id' => ['required', 'uuid', Rule::exists('core_definitions', 'id')->whereNull('deleted_at')],
            'core_box_id' => ['required', 'uuid', Rule::exists('core_boxes', 'id')->whereNull('deleted_at')],
            'quantity_made' => ['required', 'integer', 'min:1', 'max:100000'],
            'made_on' => ['required', 'date', 'before_or_equal:today'],
            'location' => ['nullable', 'string', 'max:100'],
            'use_now' => ['nullable', 'boolean'],
            'moulding_run_id' => ['nullable', 'uuid', Rule::exists('moulding_runs', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['core_box_id.required' => 'Choose the core box the cores were made in.']);

        $lot = DB::transaction(function () use ($data, $service) {
            $madeOn = Carbon::parse($data['made_on']);
            $lot = $service->make(CoreDefinition::findOrFail($data['core_definition_id']), CoreBox::findOrFail($data['core_box_id']), (int) $data['quantity_made'], $madeOn,
                empty($data['use_now']) ? ($data['location'] ?? null) : null, $data['notes'] ?? null, ! empty($data['use_now']));
            if (! empty($data['use_now'])) {
                $service->take($lot, $lot->quantity_made, 'USED', $madeOn, $data['moulding_run_id'] ?? null);
            }

            return $lot;
        });

        return redirect()->route('core-lots.show', $lot)->with('success', "Lot {$lot->lot_number}: {$lot->quantity_made} made".(empty($data['use_now']) ? '.' : ' and used.'));
    }

    public function show(CoreLot $coreLot, CoreLotService $service): View
    {
        $coreLot->load('coreDefinition', 'coreBox', 'uses.mouldingRun');

        return view('tooling::core-lots.show', [
            'lot' => $coreLot,
            'remaining' => $coreLot->remaining(),
            'runs' => MouldingRun::latest('run_date')->limit(30)->get(),
            'qcOn' => CoreRules::get('qc') === 'REQUIRED',
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

    public function editRules(): View
    {
        return view('settings.rules', [
            'title' => 'Core rules', 'intro' => 'How the whole foundry handles made cores.',
            'rules' => CoreRules::RULES, 'values' => collect(CoreRules::RULES)->map(fn ($r, $k) => CoreRules::get($k))->all(),
            'action' => route('settings.cores'), 'backUrl' => route('core-lots.index'), 'backLabel' => 'Core stock',
            'note' => 'A small foundry can leave both off: cores are recorded when made and when used. Turn the check on when cores must be inspected before they go into a mould.',
        ]);
    }

    public function updateRules(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (CoreRules::RULES as $key => [, , $options]) {
            $rules[$key] = ['required', Rule::in(array_keys($options))];
        }
        foreach ($request->validate($rules) as $key => $value) {
            CoreRules::set($key, $value);
        }

        return back()->with('success', 'Core rules saved.');
    }
}
