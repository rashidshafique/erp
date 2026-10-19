<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\FlaskSize;
use App\Models\MouldingStation;
use App\Models\MouldingWorkList;
use App\Models\MouldingWorkListItem;
use App\Models\PatternRevision;
use App\Models\ProductionOrder;
use App\Models\SandSystem;
use App\Services\Production\WorkListService;
use App\Services\Quality\Measurements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class WorkListController extends Controller
{
    public const SHIFTS = ['Morning', 'Evening', 'Night', 'General'];

    public function index(): View
    {
        return view('work-lists.index', ['lists' => MouldingWorkList::withCount('items')->orderByDesc('list_date')->orderByDesc('created_at')->paginate(25), 'shifts' => self::SHIFTS]);
    }

    public function store(Request $request, WorkListService $svc): RedirectResponse
    {
        $d = $request->validate(['list_date' => ['required', 'date'], 'shift' => ['required', Rule::in(self::SHIFTS)], 'notes' => ['nullable', 'string', 'max:1000']]);
        $list = $svc->create($d['list_date'], $d['shift'], $d['notes'] ?? null);

        return redirect()->route('work-lists.show', $list)->with('success', "List {$list->list_number} created. Add the work, then issue it.");
    }

    public function show(MouldingWorkList $list, WorkListService $svc): View
    {
        $list->load(['items.team', 'items.station', 'items.productionOrder.product', 'items.productionOrder.salesOrderLine.salesOrder.customer', 'items.patternRevision.pattern', 'items.run']);
        $orders = ProductionOrder::with(['product', 'salesOrderLine.salesOrder.customer'])->whereIn('status', ['RELEASED', 'IN_PROGRESS'])->orderBy('production_order_number')->get();
        $patterns = PatternRevision::with(['pattern', 'cavityMap.productRevision'])
            ->whereHas('pattern', fn ($q) => $q->whereNull('patterns.deleted_at'))   // archived plates leave the picker
            ->get()
            ->sortBy(fn ($p) => mb_strtolower($p->pattern->name.' '.$p->shortCode()), SORT_NATURAL)->values();
        $needed = [];
        foreach ($list->items as $i) {
            $needed[$i->id] = $svc->mouldsStillNeeded($i->productionOrder, $i->pattern_revision_id);
        }
        $stations = MouldingStation::with('defaultTeam')->where('status', 'ACTIVE')->orderBy('code')->get();
        // Patterns that make each order's product, so the pattern choice can follow the order.
        $patternSand = $patterns->mapWithKeys(fn ($p) => [$p->id => ['code' => $p->pattern->code, 'for' => $p->pattern->sand_system, 'also' => $p->pattern->compatible_sands ?? [], 'note' => $p->pattern->sand_note]])->all();
        $patternProducts = $patterns->mapWithKeys(fn ($p) => [$p->id => $p->cavityMap->map(fn ($c) => $c->productRevision?->product_id)->filter()->unique()->values()->all()])->all();
        // For each pattern: the open order it would link to if none is chosen.
        $autoOrder = $patterns->mapWithKeys(function ($p) use ($svc) {
            $o = $svc->openOrdersFor($p->id)->first();

            return [$p->id => $o ? $o->production_order_number.' · '.$o->customerLabel() : ''];
        })->all();
        $running = MouldingWorkListItem::where('status', 'IN_PROGRESS')->selectRaw('moulding_station_id, count(*) n')->groupBy('moulding_station_id')->pluck('n', 'moulding_station_id')->all();

        return view('work-lists.show', [
            'list' => $list, 'orders' => $orders, 'patterns' => $patterns, 'needed' => $needed,
            'patternPhotos' => Attachment::coversFor('pattern', $patterns->pluck('pattern_id')->unique()->values()->all()),
            'teamOf' => $stations->mapWithKeys(fn ($s) => [$s->id => $s->defaultTeam ? $s->defaultTeam->code.' — '.$s->defaultTeam->lead_name : ''])->all(), 'patternProducts' => $patternProducts, 'patternSand' => $patternSand, 'autoOrder' => $autoOrder, 'running' => $running,
            'stations' => $stations, 'flaskSizes' => FlaskSize::ordered()->where('status', 'ACTIVE')->get(),
            'sands' => SandSystem::choices(...$list->items()->pluck('sand_system')->all()), 'sandFor' => $stations->mapWithKeys(fn ($s) => [$s->id => $svc->sandFor($list, $s)])->all(),
        ]);
    }

    public function print(MouldingWorkList $list): View
    {
        $list->load(['items.team', 'items.station', 'items.productionOrder.product', 'items.productionOrder.salesOrderLine.salesOrder.customer', 'items.patternRevision.pattern']);

        return view('work-lists.print', ['list' => $list]);
    }

    public function addItem(Request $request, MouldingWorkList $list, WorkListService $svc): RedirectResponse
    {
        $d = $request->validate([
            'moulding_team_id' => ['nullable', 'uuid', 'exists:moulding_teams,id'], 'moulding_station_id' => ['required', 'uuid', 'exists:moulding_stations,id'],
            'production_order_id' => ['nullable', 'uuid', 'exists:production_orders,id'], 'pattern_revision_id' => ['required', 'uuid', 'exists:pattern_revisions,id'],
            'sand_system' => ['nullable', 'string', 'max:60'], 'planned_moulds' => ['required', 'integer', 'min:1', 'max:5000'], 'instructions' => ['nullable', 'string', 'max:1000'], 'sand_override' => ['nullable', 'boolean'],
        ]);
        try {
            $svc->addItem($list, $d);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['item' => $e->getMessage()])->withInput();
        } catch (ValidationException $e) {
            return back()->withErrors(['item' => collect($e->errors())->flatten()->first()])->withInput();
        }

        return back()->with('success', 'Added to the list.');
    }

    /** One station's work as rows: team and sand for the line, then a row per pattern plate and mould count. */
    public function addRows(Request $request, MouldingWorkList $list, WorkListService $svc): RedirectResponse
    {
        $d = $request->validate([
            'moulding_station_id' => ['required', 'uuid', 'exists:moulding_stations,id'],
            'sand_system' => ['nullable', 'string', 'max:60'], 'instructions' => ['nullable', 'string', 'max:1000'],
            'rows' => ['required', 'array', 'min:1', 'max:40'],
            'rows.*.pattern_revision_id' => ['required', 'uuid', 'exists:pattern_revisions,id'], 'rows.*.production_order_id' => ['nullable', 'uuid', 'exists:production_orders,id'],
            'rows.*.planned_moulds' => ['required', 'integer', 'min:1', 'max:5000'], 'rows.*.sand_override' => ['nullable', 'boolean'],
        ], ['rows.*.pattern_revision_id.required' => 'Choose a pattern plate in every row.', 'rows.*.planned_moulds.required' => 'Enter the mould count in every row.', 'rows.required' => 'Add at least one pattern plate.']);
        try {
            $n = $svc->addRows($list, MouldingStation::findOrFail($d['moulding_station_id']), $d['sand_system'] ?? null, $d['rows'], $d['instructions'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['item' => $e->getMessage()])->withInput();
        }

        return back()->with('success', $n.' row'.($n === 1 ? '' : 's').' added.');
    }

    public function changeStation(Request $request, MouldingWorkList $list, MouldingStation $station, WorkListService $svc): RedirectResponse
    {
        $d = $request->validate(['sand_system' => ['required', 'string', 'max:60'], 'accept_unsuited' => ['nullable', 'boolean']]);
        try {
            $n = $svc->changeStation($list, $station, $d['sand_system'], (bool) ($d['accept_unsuited'] ?? false));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['item' => $e->getMessage()]);
        }

        return back()->with('success', $n ? "Sand system updated for {$n} planned item(s) on {$station->code}." : 'Nothing planned to change on '.$station->code.'.');
    }

    public function removeItem(MouldingWorkList $list, MouldingWorkListItem $item): RedirectResponse
    {
        abort_unless($item->moulding_work_list_id === $list->id, 404);
        if ($item->status !== 'PLANNED' || $item->moulding_run_id) {
            return back()->withErrors(['item' => 'Only an item not yet started can be removed.']);
        }
        $item->delete();

        return back()->with('success', 'Item removed.');
    }

    public function issue(Request $request, MouldingWorkList $list, WorkListService $svc): RedirectResponse
    {
        $by = $request->validate(['issued_by' => ['required', 'string', 'max:100']])['issued_by'];
        try {
            $svc->issue($list, $by);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['item' => $e->getMessage()]);
        }

        return back()->with('success', 'List issued to the teams.');
    }

    public function startRun(MouldingWorkList $list, MouldingWorkListItem $item, WorkListService $svc): RedirectResponse
    {
        abort_unless($item->moulding_work_list_id === $list->id, 404);
        try {
            $run = $svc->startRun($item);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['item' => $e->getMessage()]);
        }

        $sand = app(Measurements::class)->sandStatus($run->sand_system, now());
        $msg = "Run {$run->run_number} started for team {$item->team->code}.".($sand['state'] === 'OK' ? '' : ' WARNING: '.$sand['text']);

        return redirect()->route('moulding-runs.show', $run)->with('success', $msg);
    }

    public function linkItem(MouldingWorkList $list, MouldingWorkListItem $item, WorkListService $svc): RedirectResponse
    {
        abort_unless($item->moulding_work_list_id === $list->id, 404);
        $order = $svc->relink($item);

        return $order ? back()->with('success', "Linked to {$order->production_order_number} ({$order->customerLabel()}).")
            : back()->withErrors(['item' => 'No open order needs this pattern right now. A stock order is raised when the run starts.']);
    }

    public function startStation(MouldingWorkList $list, MouldingStation $station, WorkListService $svc): RedirectResponse
    {
        try {
            $n = $svc->startStation($list, $station);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['item' => $e->getMessage()]);
        }

        return back()->with('success', "{$n} run(s) started on {$station->code}.");
    }

    public function cancel(MouldingWorkList $list): RedirectResponse
    {
        if (! in_array($list->status, ['DRAFT', 'ISSUED'], true)) {
            return back()->withErrors(['item' => "A {$list->status} list cannot be cancelled."]);
        }
        if ($list->items()->whereNotNull('moulding_run_id')->exists()) {
            return back()->withErrors(['item' => 'Runs have started from this list; it cannot be cancelled.']);
        }
        $list->update(['status' => 'CANCELLED']);

        return back()->with('success', 'List cancelled.');
    }
}
