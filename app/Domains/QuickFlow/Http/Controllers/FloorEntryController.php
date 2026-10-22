<?php

namespace App\Domains\QuickFlow\Http\Controllers;

use App\Domains\QuickFlow\Models\QuickFloorEntry;
use App\Domains\QuickFlow\Services\NextHeatPlan;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Heat;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\Pattern;
use App\Models\Pour;
use App\Models\ProductionOrder;
use App\Services\PatternCavityMap;
use App\Services\Production\WorkListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Typed-in counts, one grid row per pattern: moulds made, and moulds poured from a heat. Each row also writes the usual
 * moulding run or pour record. A row's order, station and pieces per mould are filled in from the pattern and can be changed.
 */
class FloorEntryController extends Controller
{
    private const OPEN = ['RELEASED', 'IN_PROGRESS'];

    public function __construct(private NextHeatPlan $plan, private WorkListService $workLists, private PatternCavityMap $cavityMap) {}

    public function index(): View
    {
        $orders = $this->plan->orders();
        $patterns = $this->patternChoices($orders);

        return view('quickflow::floor.index', [
            'orders' => $orders->filter(fn ($o) => $o['ordered'] > $o['poured']), 'awaiting' => $orders->filter(fn ($o) => $o['awaiting'] > 0),
            'patterns' => $patterns,
            'patternPhotos' => Attachment::coverUrlsFor('pattern', $patterns->pluck('pattern.id')->all()),
            'stations' => MouldingStation::where('status', 'ACTIVE')->orderBy('code')->get(),
            'heats' => Heat::with(['ironGrade', 'furnace', 'thermalTests'])->whereNotIn('status', ['CANCELLED', 'CLOSED'])->orderByDesc('created_at')->limit(15)->get(),
            'recent' => QuickFloorEntry::with(['productionOrder.product', 'heat', 'pattern', 'station'])->orderByDesc('created_at')->limit(15)->get(),
        ]);
    }

    public function made(Request $request): RedirectResponse
    {
        $rows = $this->validatedRows($request);

        $moulds = DB::transaction(function () use ($rows, $request) {
            $total = 0;
            foreach ($rows as $n => $row) {
                $order = ProductionOrder::lockForUpdate()->findOrFail($row['production_order_id']);
                $run = MouldingRun::create([
                    'run_number' => MouldingRun::nextRunNumber(), 'production_order_id' => $order->id, 'run_date' => $row['entry_date'],
                    'pattern_revision_id' => $row['pattern_revision_id'], 'moulding_station_id' => $row['moulding_station_id'],
                    'operator_name' => $request->input('entered_by'), 'planned_mould_qty' => $row['moulds'], 'moulds_made_qty' => $row['moulds'],
                    'accepted_mould_qty' => $row['moulds'], 'status' => 'COMPLETED', 'completed_at' => now(),
                ]);
                QuickFloorEntry::create(['kind' => QuickFloorEntry::MADE, 'moulding_run_id' => $run->id, 'entered_by' => $request->input('entered_by')] + $this->entryFields($row));
                if ($order->status === 'RELEASED') {
                    $order->update(['status' => 'IN_PROGRESS', 'actual_started_at' => now()]);
                }
                $total += $row['moulds'];
            }

            return $total;
        });

        return back()->with('success', "{$moulds} moulds recorded on ".count($rows).' '.(count($rows) === 1 ? 'pattern' : 'patterns').'.');
    }

    public function poured(Request $request): RedirectResponse
    {
        $heatId = $request->validate([
            'heat_id' => ['required', 'uuid', Rule::exists('heats', 'id')->whereNotIn('status', ['CANCELLED', 'CLOSED'])],
        ], ['heat_id.required' => 'Choose the heat the moulds were poured from.'])['heat_id'];
        $rows = $this->validatedRows($request, pouring: true);
        $heat = Heat::with(['thermalTests', 'ironGrade'])->findOrFail($heatId);
        $advice = $this->thermalAdvice($heat);

        $moulds = DB::transaction(function () use ($rows, $request, $heatId, $advice) {
            $prefix = 'P-'.now()->format('Ymd').'-';
            $last = Pour::where('pour_number', 'like', $prefix.'%')->lockForUpdate()->pluck('pour_number')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;
            $pour = Pour::create([
                'pour_number' => $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT), 'heat_id' => $heatId, 'status' => 'COMPLETED',
                'started_at' => now(), 'completed_at' => now(), 'operator_name' => $request->input('entered_by'),
                'gate_mode' => 'ADVISORY', 'gate_note' => $advice,
            ]);
            foreach ($rows as $row) {
                QuickFloorEntry::create(['kind' => QuickFloorEntry::POURED, 'pour_id' => $pour->id, 'heat_id' => $heatId, 'entered_by' => $request->input('entered_by')] + $this->entryFields($row));
            }

            return array_sum(array_column($rows, 'moulds'));
        });

        return back()->with('success', "{$moulds} moulds poured from {$heat->heat_number}.")->with('warning', $advice);
    }

    /**
     * The patterns offered in the grids, each with what a row is filled in with: its default station, the open order that needs
     * what it makes (for moulding, and the one with moulds waiting to be poured), and how many pieces of that order one mould gives.
     *
     * @param  Collection<int, array<string, mixed>>  $orders  from NextHeatPlan::orders()
     * @return Collection<int, array<string, mixed>>
     */
    private function patternChoices(Collection $orders): Collection
    {
        $awaitingByProduct = $orders->filter(fn ($o) => $o['awaiting'] > 0)->groupBy(fn ($o) => $o['order']->product_id);

        return Pattern::with(['currentRevision.cavityMap.productRevision'])->where('status', 'ACTIVE')->orderBy('name')->orderBy('code')->get()
            ->map(function (Pattern $pattern) use ($awaitingByProduct) {
                $revision = $pattern->currentRevision;
                $products = $revision ? $revision->cavityMap->map(fn ($c) => $c->productRevision?->product_id)->filter()->unique() : collect();
                $makeOrder = $revision ? $this->workLists->openOrdersFor($revision->id)->first() : null;
                $pourOrder = $products->map(fn ($id) => $awaitingByProduct->get($id)?->first()['order'])->filter()->first();

                return [
                    'pattern' => $pattern, 'revision' => $revision, 'station_id' => $pattern->preferred_moulding_station_id,
                    'make_order' => $makeOrder, 'pour_order' => $pourOrder,
                    'make_ppm' => $this->piecesPerMould($revision, $makeOrder), 'pour_ppm' => $this->piecesPerMould($revision, $pourOrder),
                ];
            });
    }

    /** Pieces of the order's product one mould of the revision gives, rounded; 1 when the cavity map does not say. */
    private function piecesPerMould(?\App\Models\PatternRevision $revision, ?ProductionOrder $order): int
    {
        if (! $revision || ! $order) {
            return 1;
        }

        return max(1, (int) round($this->cavityMap->expectedProductsPerMould($revision, $order->product_id)));
    }

    /**
     * The filled-in rows of a grid, checked. Empty rows are skipped; a row needs a pattern and a mould count. A row without an
     * order takes the pattern's open order, and is refused when there is none.
     *
     * @return array<int, array{pattern_id: string, pattern_revision_id: ?string, production_order_id: string, moulding_station_id: ?string, moulds: int, pieces_per_mould: int, entry_date: string}>
     */
    private function validatedRows(Request $request, bool $pouring = false): array
    {
        $request->validate([
            'rows' => ['required', 'array', 'max:100'],
            'rows.*.pattern_id' => ['nullable', 'uuid', Rule::exists('patterns', 'id')->whereNull('deleted_at')],
            'rows.*.moulds' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'rows.*.pieces_per_mould' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'rows.*.production_order_id' => ['nullable', 'uuid', Rule::exists('production_orders', 'id')->whereIn('status', self::OPEN)],
            'rows.*.moulding_station_id' => ['nullable', 'uuid', Rule::exists('moulding_stations', 'id')],
            'rows.*.entry_date' => ['nullable', 'date', 'before_or_equal:today'],
            'entered_by' => ['nullable', 'string', 'max:100'],
        ], [
            'rows.*.entry_date.before_or_equal' => 'A date cannot be in the future.',
            'rows.*.production_order_id.exists' => 'That order is not open.',
        ]);

        $choices = null;
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
            $choices ??= $this->patternChoices($this->plan->orders())->keyBy(fn ($c) => $c['pattern']->id);
            $choice = $choices->get($row['pattern_id']);
            $pattern = $choice['pattern'] ?? Pattern::with('currentRevision')->findOrFail($row['pattern_id']);
            $order = $row['production_order_id'] ?? null ?: ($pouring ? $choice['pour_order'] ?? null : $choice['make_order'] ?? null)?->id;
            if (! $order) {
                $errors["rows.{$n}.production_order_id"] = "Row {$line}: no open order ".($pouring ? 'has moulds of' : 'needs what').' '.$pattern->name.($pouring ? ' waiting to be poured' : ' makes').'. Choose the order.';

                continue;
            }
            $rows[$n] = [
                'pattern_id' => $pattern->id, 'pattern_revision_id' => $pattern->currentRevision?->id, 'production_order_id' => $order,
                'moulding_station_id' => $row['moulding_station_id'] ?? null ?: $pattern->preferred_moulding_station_id,
                'moulds' => (int) $row['moulds'], 'pieces_per_mould' => (int) ($row['pieces_per_mould'] ?? null ?: 1),
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

    /** @param  array<string, mixed>  $row */
    private function entryFields(array $row): array
    {
        return array_intersect_key($row, array_flip(['production_order_id', 'pattern_id', 'moulding_station_id', 'moulds', 'pieces_per_mould', 'entry_date']));
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
