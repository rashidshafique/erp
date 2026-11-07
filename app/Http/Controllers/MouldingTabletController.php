<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Models\MouldCount;
use App\Models\MouldingStation;
use App\Models\MouldingWorkList;
use App\Models\MouldingWorkListItem;
use App\Models\PatternRevision;
use App\Services\PatternCavityMap;
use App\Services\Production\FloorAlerts;
use App\Services\Production\MouldCounter;
use App\Services\Production\WorkListService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The moulding supervisor's tablet (design F3·1-F3·5, F3·E): the station's list for today, moulds made per row, the count
 * before a pour with a reason per short pattern, and what is ready for the heat. Every write goes through the work list
 * service and the mould counter, the same as the full pages.
 */
class MouldingTabletController extends Controller
{
    public function __construct(private PatternCavityMap $map) {}

    /** F3·1 today's list for a station, active rows first by due date, finished rows folded; F3·E carried-over rows. */
    public function list(Request $request): View
    {
        $stations = MouldingStation::where('status', 'ACTIVE')->orderBy('code')->get();
        $today = $this->items()->whereHas('workList', fn ($q) => $q->whereDate('list_date', today())->whereIn('status', ['ISSUED', 'COMPLETED']))->get();
        $station = $stations->firstWhere('id', $request->query('station'))
            ?? $stations->firstWhere('id', $today->countBy('moulding_station_id')->sortDesc()->keys()->first())
            ?? $stations->first();
        $rows = $today->where('moulding_station_id', $station?->id);

        return view('floor.moulding.list', [
            'stations' => $stations, 'station' => $station, 'list' => $rows->first()?->workList,
            'draft' => MouldingWorkList::whereDate('list_date', today())->where('status', 'DRAFT')->exists(),
            'active' => $rows->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->sortBy(fn ($i) => $i->productionOrder?->required_date?->timestamp ?? PHP_INT_MAX)->values(),
            'done' => $rows->whereIn('status', ['DONE', 'SKIPPED'])->values(),
            'carried' => $rows->isEmpty() && $station ? $this->carried($station) : collect(),
            'tabs' => $this->tabs('list'),
        ]);
    }

    /** F3·2 moulds made now on one row, with damaged and downtime; the first save starts the run. */
    public function item(MouldingWorkListItem $item): View
    {
        $item->load(['workList', 'station', 'team', 'productionOrder', 'patternRevision.pattern', 'run']);

        return view('floor.moulding.item', ['item' => $item, 'run' => $item->run, 'cavities' => $this->castingsPerMould($item->patternRevision), 'tabs' => $this->tabs('list')]);
    }

    public function save(Request $request, MouldingWorkListItem $item, WorkListService $svc): RedirectResponse
    {
        $d = $request->validate([
            'made' => ['required', 'integer', 'min:0', 'max:100000'], 'damaged' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'downtime' => ['nullable', 'integer', 'min:0', 'max:1440'], 'downtime_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $made = (int) $d['made'];
        $damaged = (int) ($d['damaged'] ?? 0);
        try {
            $run = $item->run ?? $svc->startRun($item);
            $run = $svc->addOutput($run, $made, $damaged, (int) ($d['downtime'] ?? 0), $d['downtime_reason'] ?? null, $request->boolean('complete'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['made' => $e->getMessage()])->withInput();
        }

        return redirect()->route('floor.moulding.list', ['station' => $item->moulding_station_id])
            ->with('success', ($made - $damaged)." moulds saved on {$run->run_number}".($run->status === 'COMPLETED' ? '; run completed.' : '.'));
    }

    /** F3·3 count before pouring: every pattern waiting for metal, with what records say. */
    public function count(MouldCounter $counter): View
    {
        return view('floor.moulding.count', ['waiting' => $counter->waiting(), 'heat' => $this->nextHeat(), 'tabs' => $this->tabs('count')]);
    }

    /** Saves a count with no shortfall straight away; a short pattern goes to F3·4 to say why. */
    public function review(Request $request, MouldCounter $counter, FloorAlerts $alerts): View|RedirectResponse
    {
        $counted = $this->counted($request);
        if (! $counted) {
            return back()->withErrors(['counted' => 'Enter the moulds counted for at least one pattern.'])->withInput();
        }
        $short = $this->short($counter->waiting(), $counted);
        if ($short->isEmpty()) {
            return $this->record($counter, $alerts, $counted, [], null, $request->input('heat_id'));
        }

        return $this->reasonView($short, $counted, $request->input('heat_id'));
    }

    /** F3·4 the reason for each short pattern, then the count is saved and the moulds written off. */
    public function store(Request $request, MouldCounter $counter, FloorAlerts $alerts): View|RedirectResponse
    {
        $counted = $this->counted($request);
        $d = $request->validate([
            'reason' => ['nullable', 'array'], 'reason.*' => ['nullable', Rule::in(array_keys(MouldCount::REASONS))], 'note' => ['nullable', 'string', 'max:300'],
        ]);
        $short = $this->short($counter->waiting(), $counted);
        if ($short->keys()->contains(fn ($id) => empty($d['reason'][$id]))) {
            return $this->reasonView($short, $counted, $request->input('heat_id'), 'Pick why for each short pattern.');
        }

        return $this->record($counter, $alerts, $counted, $d['reason'] ?? [], $d['note'] ?? null, $request->input('heat_id'));
    }

    /** F3·5 what the count left ready for the heat: moulds, castings and metal per pattern. */
    public function ready(): View|RedirectResponse
    {
        $ready = session('moulding_ready');
        if (! $ready) {
            return redirect()->route('floor.moulding.count');
        }

        return view('floor.moulding.ready', $ready + ['heat' => $ready['heat_id'] ? Heat::with('furnace')->find($ready['heat_id']) : null, 'tabs' => $this->tabs('count')]);
    }

    /**
     * @param  Collection<string, array{expected: int, counted: int, revision: PatternRevision}>  $short
     * @param  array<string, int>  $counted
     */
    private function reasonView(Collection $short, array $counted, ?string $heatId, ?string $error = null): View
    {
        $view = view('floor.moulding.reason', ['short' => $short, 'counted' => $counted, 'heatId' => $heatId, 'tabs' => $this->tabs('count')]);

        return $error ? $view->withErrors(['reason' => $error]) : $view;
    }

    /**
     * Records the count, one call per short pattern so each keeps its own reason, then tells the next hats once.
     *
     * @param  array<string, int>  $counted
     * @param  array<string, string|null>  $reasons
     */
    private function record(MouldCounter $counter, FloorAlerts $alerts, array $counted, array $reasons, ?string $note, ?string $heatId): RedirectResponse
    {
        $heatId = $heatId && Heat::whereKey($heatId)->exists() ? $heatId : null;
        $short = $this->short($counter->waiting(), $counted);
        $by = Auth::user()?->name;
        $calls = $short->keys()->map(fn (string $id) => [[$id => $counted[$id]], $reasons[$id] ?? null])->all();
        $rest = array_diff_key($counted, $short->all());
        if ($rest) {
            $calls[] = [$rest, null];
        }
        $total = ['counted' => 0, 'written_off' => 0];
        try {
            foreach ($calls as [$part, $reason]) {
                $res = $counter->record($part, $reason, $note, $heatId, $by);
                $total = ['counted' => $total['counted'] + $res['counted'], 'written_off' => $total['written_off'] + $res['written_off']];
            }
        } catch (InvalidArgumentException $e) {
            return redirect()->route('floor.moulding.count')->withErrors(['counted' => $e->getMessage()]);
        }
        $alerts->mouldsCounted($total['counted'], Auth::user());
        $alerts->mouldsShort($total['written_off'], Auth::user());

        return redirect()->route('floor.moulding.count.ready')->with('moulding_ready', [
            'rows' => $this->readyRows($counted), 'written_off' => $total['written_off'], 'heat_id' => $heatId, 'by' => $by, 'at' => now()->format('H:i'),
        ]);
    }

    /** @return list<array{code: string, name: string, moulds: int, castings: ?int, kg: ?float}> */
    private function readyRows(array $counted): array
    {
        $revisions = PatternRevision::with(['pattern', 'cavityMap'])->whereIn('id', array_keys($counted))->get()->keyBy('id');

        return collect($counted)->filter(fn (int $n, string $id) => $n > 0 && $revisions->has($id))->map(function (int $n, string $id) use ($revisions) {
            $metal = $this->map->metalPerMould($revisions[$id]);
            $perMould = $this->castingsPerMould($revisions[$id]);

            return ['code' => $revisions[$id]->pattern->code, 'name' => $revisions[$id]->pattern->name, 'moulds' => $n,
                'castings' => $perMould ? $n * $perMould : null, 'kg' => $metal ? round($n * $metal['kg'], 1) : null];
        })->values()->all();
    }

    private function castingsPerMould(?PatternRevision $revision): ?int
    {
        if (! $revision) {
            return null;
        }

        return $this->map->metalPerMould($revision)['castings'] ?? ($revision->cavities_per_mould ? (int) $revision->cavities_per_mould : null);
    }

    /** @return array<string, int> pattern revision id => moulds counted, empty fields left out */
    private function counted(Request $request): array
    {
        $d = $request->validate(['counted' => ['required', 'array'], 'counted.*' => ['nullable', 'integer', 'min:0', 'max:100000'], 'heat_id' => ['nullable', 'uuid']]);

        return array_map('intval', array_filter($d['counted'], fn ($n) => $n !== null && $n !== ''));
    }

    /**
     * @param  Collection<string, array{expected: int}>  $waiting
     * @param  array<string, int>  $counted
     * @return Collection<string, array{expected: int, counted: int, revision: PatternRevision}>
     */
    private function short(Collection $waiting, array $counted): Collection
    {
        return collect($counted)->filter(fn (int $n, string $id) => $waiting->has($id) && $n < $waiting[$id]['expected'])
            ->map(fn (int $n, string $id) => $waiting[$id] + ['counted' => $n]);
    }

    /** The heat the count is for: the one running, else the next one planned. */
    private function nextHeat(): ?Heat
    {
        return Heat::with('furnace')->whereIn('status', Heat::RUNNING_STATUSES)->latest('actual_start_at')->first()
            ?? Heat::with('furnace')->where('status', 'PLANNED')->orderByRaw('planned_start_at is null')->orderBy('planned_start_at')->first();
    }

    /** Rows from earlier issued lists at this station that are not finished and were not carried onto a later list. */
    private function carried(MouldingStation $station): Collection
    {
        return $this->items()->where('moulding_station_id', $station->id)->whereIn('status', ['PLANNED', 'IN_PROGRESS'])
            ->whereHas('workList', fn ($q) => $q->whereDate('list_date', '<', today())->where('status', 'ISSUED'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('moulding_work_list_items as later')->whereColumn('later.carried_from_item_id', 'moulding_work_list_items.id'))
            ->get()->sortByDesc(fn ($i) => $i->workList->list_date)->values();
    }

    /** @return Builder<MouldingWorkListItem> */
    private function items(): Builder
    {
        return MouldingWorkListItem::with(['workList', 'station', 'team', 'productionOrder', 'patternRevision.pattern', 'run']);
    }

    /**
     * Home · List · Count (Scan is added by the view). Count shows how many patterns wait, amber while a heat runs that nobody
     * has counted for since it started.
     *
     * @return list<array{label: string, href: string, active?: bool, count?: int, tone?: ?string}>
     */
    private function tabs(string $active): array
    {
        $waiting = app(MouldCounter::class)->waiting()->count();
        $running = Heat::whereIn('status', Heat::RUNNING_STATUSES)->latest('actual_start_at')->first();
        $uncounted = $running && ! MouldCount::where('counted_at', '>=', $running->actual_start_at ?? $running->created_at)->exists();

        return [
            ['label' => 'Home', 'href' => route('home')],
            ['label' => 'List', 'href' => route('floor.moulding.list'), 'active' => $active === 'list'],
            ['label' => 'Count', 'href' => route('floor.moulding.count'), 'active' => $active === 'count', 'count' => $waiting, 'tone' => $uncounted && $waiting > 0 ? 'warn' : null],
        ];
    }
}
