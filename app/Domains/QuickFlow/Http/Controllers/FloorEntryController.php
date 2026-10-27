<?php

namespace App\Domains\QuickFlow\Http\Controllers;

use App\Domains\QuickFlow\Models\QuickFloorEntry;
use App\Domains\QuickFlow\Services\NextHeatPlan;
use App\Domains\QuickFlow\Services\QuickFloorLinker;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Heat;
use App\Models\MouldingStation;
use App\Models\Pattern;
use App\Models\Pour;
use App\Models\ProductionOrder;
use App\Services\Planning\OrderPlanner;
use App\Services\QualityGates;
use App\Support\PouringRules;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Typed-in counts, one grid row per pattern: moulds made, and moulds poured from a heat. A row goes to the order picked, else
 * the open order that matches its pattern; with no order it is stored anyway and waits to be linked, by hand or when a
 * matching order opens. Moulds made write their moulding run once they have an order; a pour is written straight away.
 */
class FloorEntryController extends Controller
{
    private const OPEN = QuickFloorLinker::OPEN;

    public function __construct(private NextHeatPlan $plan, private QuickFloorLinker $linker) {}

    public function index(Request $request, OrderPlanner $planner): View
    {
        $planDay = $request->validate(['plan' => ['nullable', 'date']])['plan'] ?? null;
        $orders = $this->plan->orders();
        $patterns = $this->patternChoices($orders);
        $waiting = QuickFloorEntry::unlinked()->with(['pattern', 'patternRevision.cavityMap.productRevision', 'station', 'heat'])->orderBy('entry_date')->orderBy('created_at')->get();

        return view('quickflow::floor.index', [
            'orders' => $orders->filter(fn ($o) => $o['ordered'] > $o['poured']), 'awaiting' => $orders->filter(fn ($o) => $o['awaiting'] > 0),
            'openOrders' => $orders,
            'patterns' => $patterns,
            'patternPhotos' => Attachment::coverUrlsFor('pattern', $patterns->pluck('pattern.id')->merge($waiting->pluck('pattern_id'))->all()),
            'stations' => MouldingStation::where('status', 'ACTIVE')->orderBy('code')->get(),
            'heats' => $this->pourableHeats()->with(['ironGrade', 'furnace', 'thermalTests'])->orderByDesc('created_at')->limit(15)->get(),
            'waiting' => $waiting->map(fn ($e) => ['entry' => $e, 'products' => $this->linker->productsOf($e->patternRevision)]),
            'recent' => QuickFloorEntry::with(['productionOrder.product', 'heat', 'pattern', 'station'])->orderByDesc('created_at')->limit(15)->get(),
            'plannedRows' => $planDay ? $this->plannedRows($planner, Carbon::parse($planDay)) : [],
        ]);
    }

    /**
     * The planner's first shift for the day as grid rows: pattern, order and station, with the moulds planned shown as a hint only.
     *
     * @return list<array{pattern_id: string, production_order_id: string, moulding_station_id: string, planned: int}>
     */
    private function plannedRows(OrderPlanner $planner, Carbon $day): array
    {
        $rows = [];
        foreach ($planner->plan($day)['suggestion'] as ['station' => $station, 'rows' => $planned]) {
            foreach ($planned as $r) {
                $rows[] = ['pattern_id' => $r['pattern']->pattern_id, 'production_order_id' => $r['order']->id, 'moulding_station_id' => $station->id, 'planned' => $r['moulds']];
            }
        }

        return $rows;
    }

    public function made(Request $request): RedirectResponse
    {
        $rows = $this->validatedRows($request);

        [$moulds, $waiting] = DB::transaction(function () use ($rows, $request) {
            $total = 0;
            $waiting = 0;
            foreach ($rows as $row) {
                $entry = QuickFloorEntry::create(['kind' => QuickFloorEntry::MADE, 'entered_by' => $request->input('entered_by')] + $this->entryFields($row));
                if ($row['production_order_id']) {
                    $this->linker->link($entry, ProductionOrder::findOrFail($row['production_order_id']));
                } else {
                    $waiting++;
                }
                $total += $row['moulds'];
            }

            return [$total, $waiting];
        });

        return back()->with('success', "{$moulds} moulds recorded on ".count($rows).' '.(count($rows) === 1 ? 'pattern' : 'patterns').'.'.$this->waitingNote($waiting))
            ->with('warning', $this->unlinkedPatternsWarning($rows));
    }

    public function poured(Request $request, QualityGates $gates): RedirectResponse
    {
        $heatId = $request->validate([
            'heat_id' => ['required', 'uuid', Rule::exists('heats', 'id')->where(fn ($q) => $q->whereIn('id', $this->pourableHeats()->select('id')))],
        ], ['heat_id.required' => 'Choose the heat the moulds were poured from.'])['heat_id'];
        $rows = $this->validatedRows($request, pouring: true);
        $heat = Heat::with(['thermalTests', 'ironGrade', 'furnace'])->findOrFail($heatId);
        // The pouring gate as set for the furnace type. Pours are often typed in afterwards, so the test-age limit is not applied.
        $mode = PouringRules::gateMode($heat->furnace?->furnace_type ?? $heat->process_type);
        $gate = $mode === 'OFF' ? ['ok' => true, 'reasons' => []] : $gates->pourGate($heat, checkAge: false);
        if (! $gate['ok'] && $mode === 'ENFORCED') {
            throw ValidationException::withMessages(['heat_id' => "Moulds cannot be poured from {$heat->heat_number}. ".implode(' ', $gate['reasons'])]);
        }
        $advice = $gate['ok'] ? $this->thermalAdvice($heat) : 'Poured without a clear check: '.implode(' ', $gate['reasons']);

        // A pour typed in afterwards belongs to the day of its rows (the latest, when they differ), at the time of entry.
        $poured = Carbon::parse(max(array_column($rows, 'entry_date')))->setTimeFrom(now());

        [$moulds, $waiting] = DB::transaction(function () use ($rows, $request, $heatId, $advice, $poured, $mode) {
            $prefix = 'P-'.$poured->format('Ymd').'-';
            $last = Pour::where('pour_number', 'like', $prefix.'%')->lockForUpdate()->pluck('pour_number')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;
            $pour = Pour::create([
                'pour_number' => $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT), 'heat_id' => $heatId, 'status' => 'COMPLETED',
                'started_at' => $poured, 'completed_at' => $poured, 'operator_name' => $request->input('entered_by'),
                'gate_mode' => $mode, 'gate_note' => $advice,
            ]);
            foreach ($rows as $row) {
                $entry = QuickFloorEntry::create(['kind' => QuickFloorEntry::POURED, 'pour_id' => $pour->id, 'heat_id' => $heatId, 'entered_by' => $request->input('entered_by')] + $this->entryFields($row));
                if ($row['production_order_id']) {
                    $this->linker->link($entry, ProductionOrder::findOrFail($row['production_order_id']));
                }
            }

            return [array_sum(array_column($rows, 'moulds')), count(array_filter($rows, fn ($r) => ! $r['production_order_id']))];
        });

        return back()->with('success', "{$moulds} moulds poured from {$heat->heat_number}.".$this->waitingNote($waiting))->with('warning', trim(($advice ?? '').' '.($this->unlinkedPatternsWarning($rows) ?? '')) ?: null);
    }

    /** Heats moulds can be poured from: not cancelled, and a closed one only for a couple of days so yesterday's campaign can still be entered. */
    private function pourableHeats(): Builder
    {
        return Heat::where(fn ($q) => $q->whereNotIn('status', ['CANCELLED', 'CLOSED'])
            ->orWhere(fn ($q) => $q->where('status', 'CLOSED')->where('closed_at', '>=', today()->subDays(2))));
    }

    /** Links an entry that was stored without an order. */
    public function link(Request $request, QuickFloorEntry $entry): RedirectResponse
    {
        $orderId = $request->validate([
            'production_order_id' => ['required', 'uuid', Rule::exists('production_orders', 'id')->whereIn('status', self::OPEN)],
        ], ['production_order_id.required' => 'Choose the order.', 'production_order_id.exists' => 'That order is not open.'])['production_order_id'];
        $order = ProductionOrder::findOrFail($orderId);

        $this->linker->link($entry, $order);

        return back()->with('success', "{$entry->moulds} ".($entry->kind === QuickFloorEntry::MADE ? 'moulds made' : 'moulds poured')." linked to {$order->production_order_number}.");
    }

    /** Patterns in the rows that are not linked to any product, so their moulds cannot go to an order on their own. */
    private function unlinkedPatternsWarning(array $rows): ?string
    {
        $names = collect($rows)->pluck('unlinked_pattern')->filter()->unique()->values();
        if ($names->isEmpty()) {
            return null;
        }

        return $names->implode(', ').' '.($names->count() === 1 ? 'is' : 'are').' not linked to any product, so '.($names->count() === 1 ? 'its' : 'their')
            .' moulds cannot go to an order on their own. Link the product on the pattern\'s cavity map, or link the entries by hand.';
    }

    private function waitingNote(int $waiting): string
    {
        return $waiting ? " {$waiting} ".($waiting === 1 ? 'row is' : 'rows are').' waiting for an order; link '.($waiting === 1 ? 'it' : 'them').' below, or '.($waiting === 1 ? 'it links' : 'they link').' when a matching order opens.' : '';
    }

    /**
     * The patterns offered in the grids, each with what a row is filled in with: its default station, the open order that needs
     * what it makes (for moulding, and the one with moulds waiting to be poured), and the castings per mould its cavity map gives
     * (null when it has no map, so the row asks for the cavity count).
     *
     * @param  Collection<int, array<string, mixed>>  $orders  from NextHeatPlan::orders()
     * @return Collection<int, array<string, mixed>>
     */
    private function patternChoices(Collection $orders): Collection
    {
        return Pattern::with(['currentRevision.cavityMap.productRevision'])->where('status', 'ACTIVE')->orderBy('name')->orderBy('code')->get()
            ->map(function (Pattern $pattern) use ($orders) {
                $revision = $pattern->currentRevision;
                $makeOrder = $this->linker->makeOrderFor($revision);
                $pourOrder = $this->linker->pourOrderFor($revision, $orders);

                return [
                    'pattern' => $pattern, 'revision' => $revision, 'station_id' => $pattern->preferred_moulding_station_id,
                    'make_order' => $makeOrder, 'pour_order' => $pourOrder, 'cavities' => $this->linker->cavitiesFromMap($revision),
                ];
            });
    }

    /**
     * The filled-in rows of a grid, checked. Empty rows are skipped; a row needs a pattern and a mould count, and the cavity count
     * when the pattern has no cavity map. A row without an order takes the pattern's open order, and is kept without one when
     * none is open.
     *
     * @return array<int, array{pattern_id: string, pattern_revision_id: ?string, production_order_id: ?string, moulding_station_id: ?string, moulds: int, pieces_per_mould: int, entry_date: string}>
     */
    private function validatedRows(Request $request, bool $pouring = false): array
    {
        $request->validate([
            'rows' => ['required', 'array', 'max:100'],
            'rows.*.pattern_id' => ['nullable', 'uuid', Rule::exists('patterns', 'id')->whereNull('deleted_at')],
            'rows.*.moulds' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'rows.*.cavities' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'rows.*.production_order_id' => ['nullable', 'uuid', Rule::exists('production_orders', 'id')->whereIn('status', self::OPEN)],
            'rows.*.moulding_station_id' => ['nullable', 'uuid', Rule::exists('moulding_stations', 'id')],
            'rows.*.entry_date' => ['nullable', 'date', 'before_or_equal:today'],
            'entered_by' => ['nullable', 'string', 'max:100'],
        ], [
            'rows.*.entry_date.before_or_equal' => 'A date cannot be in the future.',
            'rows.*.production_order_id.exists' => 'That order is not open.',
        ]);

        $planOrders = null;
        $errors = [];
        $rows = [];
        foreach ((array) $request->input('rows', []) as $n => $row) {
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $row);
            if (blank($row['pattern_id'] ?? null) && blank($row['moulds'] ?? null)) {
                continue;
            }
            $line = is_numeric($n) ? (int) $n + 1 : $n;
            if (blank($row['pattern_id'] ?? null)) {
                $errors["rows.{$n}.pattern_id"] = "Row {$line}: choose the pattern.";

                continue;
            }
            if (blank($row['moulds'] ?? null)) {
                $errors["rows.{$n}.moulds"] = "Row {$line}: enter how many moulds.";

                continue;
            }
            $pattern = Pattern::with('currentRevision.cavityMap.productRevision')->findOrFail($row['pattern_id']);
            $revision = $pattern->currentRevision;
            $fromMap = $this->linker->cavitiesFromMap($revision);
            if ($fromMap === null && blank($row['cavities'] ?? null)) {
                $errors["rows.{$n}.cavities"] = "Row {$line}: {$pattern->name} has no cavity map. Enter how many cavities it has.";

                continue;
            }
            $order = $row['production_order_id'] ?? null ?: null;
            if (! $order) {
                $order = $pouring
                    ? $this->linker->pourOrderFor($revision, $planOrders ??= $this->plan->orders())?->id
                    : $this->linker->makeOrderFor($revision)?->id;
            }
            $rows[$n] = [
                'pattern_id' => $pattern->id, 'pattern_revision_id' => $revision?->id, 'production_order_id' => $order,
                'moulding_station_id' => $row['moulding_station_id'] ?? null ?: $pattern->preferred_moulding_station_id,
                'moulds' => (int) $row['moulds'],
                // With a cavity map the pieces follow it (and the order's product once linked); without one, the typed cavity count.
                'pieces_per_mould' => $fromMap !== null ? $this->linker->piecesPerMould($revision, null) : (int) $row['cavities'],
                'unlinked_pattern' => $fromMap === null ? $pattern->name : null,
                'entry_date' => $row['entry_date'] ?? null ?: today()->toDateString(),
            ];
        }
        if (! $rows && ! $errors) {
            $errors['rows'] = 'Fill in at least one row: the pattern and how many moulds.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    /**
     * The stored fields of a row; its order is set by QuickFloorLinker::link, which also writes a moulding run.
     *
     * @param  array<string, mixed>  $row
     */
    private function entryFields(array $row): array
    {
        return array_intersect_key($row, array_flip(['pattern_id', 'pattern_revision_id', 'moulding_station_id', 'moulds', 'pieces_per_mould', 'entry_date']));
    }

    /** What the latest thermal test on the heat says. Shown and recorded on the pour, never a reason to refuse it. */
    private function thermalAdvice(Heat $heat): ?string
    {
        $latest = $heat->thermalTests->first();
        if (! $latest) {
            return "No thermal test has been recorded on {$heat->heat_number}.";
        }
        if ($latest->result === 'PASS') {
            return null;
        }
        if ($latest->result === 'FAIL') {
            return 'The latest thermal test on '.$heat->heat_number.' is out of limits: '.collect($latest->findings)->take(2)->implode(' ');
        }

        return "The latest thermal test on {$heat->heat_number} could not be judged against a window.";
    }
}
