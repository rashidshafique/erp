<?php

namespace App\Services\Production;

use App\Models\Heat;
use App\Models\MouldAssembly;
use App\Models\MouldConfiguration;
use App\Models\MouldingRun;
use App\Models\Pour;
use App\Models\PourMouldAssembly;
use App\Models\ProductionOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records a pour in one go: the pour, the moulds it filled, its completion and its casting lots. Moulds are taken from those
 * waiting for the pattern, oldest first, preferring the row's order. When fewer are waiting than were poured, accepted moulds not
 * yet assembled are assembled, and any still short are written as a run "made, not entered", so a foundry that only types its
 * pours still gets lots. Lot grouping, first-iron holds and completion at pour follow their rules as for any pour.
 */
class OneStepPour
{
    public function __construct(private PourAssembler $pourAssembler, private MouldAssembler $mouldAssembler, private LotFactory $lots, private Containment $containment, private CupolaTimeline $timeline) {}

    /**
     * @param  list<array{pattern_revision_id: ?string, production_order_id: ?string, moulds: int, moulding_station_id?: ?string}>  $rows  rows without an order or pattern revision get no moulds
     * @param  array{at?: CarbonInterface, operator?: ?string, gate_mode?: ?string, gate_note?: ?string, temperature?: ?float, weight?: ?float, notes?: ?string}  $details
     * @return array{pour: Pour, lots: int, warnings: list<string>}
     */
    public function record(Heat $heat, array $rows, array $details = []): array
    {
        return $this->pour($heat, $details, fn (Pour $pour) => $this->attachRows($pour, $rows));
    }

    /**
     * The same, for moulds picked one by one (the pour screen's ticked moulds).
     *
     * @param  list<string>  $assemblyIds
     * @param  array{at?: CarbonInterface, operator?: ?string, gate_mode?: ?string, gate_note?: ?string, temperature?: ?float, weight?: ?float, notes?: ?string}  $details
     * @return array{pour: Pour, lots: int, warnings: list<string>}
     */
    public function recordMoulds(Heat $heat, array $assemblyIds, array $details = []): array
    {
        return $this->pour($heat, $details, function (Pour $pour) use ($assemblyIds) {
            $this->pourAssembler->attach($pour, $assemblyIds);

            return [];
        });
    }

    /** @param  \Closure(Pour): list<string>  $attach  puts the moulds on the pour, returning any warnings */
    private function pour(Heat $heat, array $details, \Closure $attach): array
    {
        $at = $details['at'] ?? now();

        return DB::transaction(function () use ($heat, $details, $at, $attach) {
            $pour = Pour::create([
                'pour_number' => self::nextNumber($at), 'heat_id' => $heat->id, 'status' => 'IN_PROGRESS', 'started_at' => $at,
                'operator_name' => $details['operator'] ?? null, 'gate_mode' => $details['gate_mode'] ?? null, 'gate_note' => $details['gate_note'] ?? null,
                'notes' => $details['notes'] ?? null,
            ]);
            $pour->is_first_iron = $this->timeline->onPourStart($pour);
            $pour->save();

            $warnings = $attach($pour);
            $pour->update([
                'status' => 'COMPLETED', 'completed_at' => $at,
                'pour_temperature_c' => $details['temperature'] ?? null, 'total_poured_weight_kg' => $details['weight'] ?? null,
            ]);
            $made = $this->lots->createForPour($pour->fresh());
            $held = $this->containment->message($this->containment->apply($heat));

            return ['pour' => $pour->fresh(), 'lots' => $made['lots'], 'warnings' => array_values(array_filter([...$warnings, ...$made['warnings'], $held]))];
        });
    }

    /**
     * Puts the moulds of rows on a pour already recorded, such as poured moulds typed in before their order was open, and makes
     * their lots. Moulds already given lots are left as they are.
     *
     * @param  list<array{pattern_revision_id: ?string, production_order_id: ?string, moulds: int, moulding_station_id?: ?string}>  $rows
     * @return array{lots: int, warnings: list<string>}
     */
    public function addToPour(Pour $pour, array $rows): array
    {
        return DB::transaction(function () use ($pour, $rows) {
            $warnings = $this->attachRows($pour, $rows, late: true);
            $made = $this->lots->createForPour($pour->fresh());

            return ['lots' => $made['lots'], 'warnings' => [...$warnings, ...$made['warnings']]];
        });
    }

    /** @return list<string> */
    private function attachRows(Pour $pour, array $rows, bool $late = false): array
    {
        $warnings = [];
        foreach ($rows as $row) {
            $order = filled($row['production_order_id'] ?? null) ? ProductionOrder::find($row['production_order_id']) : null;
            if (! $order || blank($row['pattern_revision_id'] ?? null) || (int) $row['moulds'] < 1) {
                continue;
            }
            $ids = $this->mouldsFor($row['pattern_revision_id'], $order, (int) $row['moulds'], $row['moulding_station_id'] ?? null, $pour, $warnings);
            if ($ids) {
                $this->pourAssembler->attach($pour, $ids, $late);
            }
        }

        return $warnings;
    }

    /**
     * Ids of $wanted moulds of the pattern revision ready to pour, the row's order first, then oldest. Makes up a shortfall from
     * accepted moulds not yet assembled, then from a run written for the moulds nobody entered as made.
     *
     * @param  list<string>  $warnings
     * @return list<string>
     */
    private function mouldsFor(string $revisionId, ProductionOrder $order, int $wanted, ?string $stationId, Pour $pour, array &$warnings): array
    {
        $waiting = fn () => MouldAssembly::query()->select('mould_assemblies.*')
            ->join('moulding_runs', 'moulding_runs.id', '=', 'mould_assemblies.moulding_run_id')
            ->where('moulding_runs.pattern_revision_id', $revisionId)->whereIn('moulding_runs.production_order_id', ProductionOrder::whereIn('status', ['RELEASED', 'IN_PROGRESS'])->select('id'))
            ->where('mould_assemblies.status', 'PREPARED')->whereNotIn('mould_assemblies.id', PourMouldAssembly::select('mould_assembly_id'))
            ->orderByRaw('CASE WHEN moulding_runs.production_order_id = ? THEN 0 ELSE 1 END', [$order->id])
            ->orderBy('mould_assemblies.assembled_at')->orderBy('mould_assemblies.assembly_number')
            ->limit($wanted)->pluck('mould_assemblies.id')->all();

        $ids = $waiting();
        if (count($ids) < $wanted) {
            $this->assembleWaitingRuns($revisionId, $wanted - count($ids));
            $ids = $waiting();
        }
        $short = $wanted - count($ids);
        if ($short > 0) {
            $run = MouldingRun::create([
                'run_number' => MouldingRun::nextRunNumber(), 'production_order_id' => $order->id, 'pattern_revision_id' => $revisionId,
                'moulding_station_id' => $stationId, 'run_date' => $pour->started_at ?? now(), 'status' => 'COMPLETED', 'completed_at' => $pour->started_at ?? now(),
                'planned_mould_qty' => $short, 'moulds_made_qty' => $short, 'accepted_mould_qty' => $short,
                'notes' => "Made, not entered: written when pour {$pour->pour_number} recorded more moulds than were waiting.",
            ]);
            $order->markInProgress();
            try {
                $this->mouldAssembler->assemble($run, $short, $this->configurationId(), 2);
            } catch (InvalidArgumentException $e) {
                $warnings[] = "{$short} poured ".($short === 1 ? 'mould' : 'moulds')." of {$order->production_order_number} could not be recorded as moulds, so they have no lot: {$e->getMessage()}";

                return $ids;
            }
            $ids = $waiting();
        }

        return $ids;
    }

    /** Assembles accepted moulds of the revision's open runs that were never assembled, oldest run first, up to $needed. */
    private function assembleWaitingRuns(string $revisionId, int $needed): void
    {
        $runs = MouldingRun::withCount('mouldAssemblies')->where('pattern_revision_id', $revisionId)->whereIn('status', ['IN_PROGRESS', 'COMPLETED', 'ON_HOLD'])
            ->whereIn('production_order_id', ProductionOrder::whereIn('status', ['RELEASED', 'IN_PROGRESS'])->select('id'))
            ->orderBy('run_date')->orderBy('created_at')->get();
        foreach ($runs as $run) {
            $open = min($needed, (int) $run->accepted_mould_qty - (int) $run->mould_assemblies_count);
            if ($open < 1) {
                continue;
            }
            try {
                $this->mouldAssembler->assemble($run, $open, $run->mould_configuration_id ?? $this->configurationId(), 2);
                $needed -= $open;
            } catch (InvalidArgumentException) {
                continue;   // flasks short under a blocking rule, or no cavity map: these moulds stay as they are
            }
            if ($needed < 1) {
                return;
            }
        }
    }

    private function configurationId(): ?string
    {
        return MouldConfiguration::where('is_active', true)->orderBy('name')->value('id');
    }

    /** P-YYYYMMDD-NNN, numbered within the day the pour belongs to. */
    public static function nextNumber(CarbonInterface $at): string
    {
        $prefix = 'P-'.$at->format('ymd').'-';
        $last = Pour::where('pour_number', 'like', $prefix.'%')->lockForUpdate()->pluck('pour_number')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 2, '0', STR_PAD_LEFT);
    }
}
