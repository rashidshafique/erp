<?php

namespace App\Services\Production;

use App\Models\MouldAssembly;
use App\Models\MouldCount;
use App\Models\MouldingRun;
use App\Models\PatternRevision;
use App\Models\PourMouldAssembly;
use App\Models\ProductionOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The mould count before a pour. The records say how many moulds of each pattern wait for metal: assembled moulds not yet poured,
 * plus accepted moulds of a run not yet assembled. The floor counts what is really there; a shortfall is written off, first from
 * the unassembled moulds of the oldest runs, then from the oldest assembled moulds (scrapped, flasks given back). Each mould written
 * off leaves its run's accepted moulds for its damaged ones, so the order needs it again and the next moulding list makes it.
 */
class MouldCounter
{
    public function __construct(private FlaskManager $flasks) {}

    /**
     * Moulds waiting for metal by pattern revision, oldest first. Unassembled moulds count only for orders still open.
     *
     * @return Collection<string, array{revision: PatternRevision, assembled: int, unassembled: int, expected: int, oldest: mixed}>
     */
    public function waiting(): Collection
    {
        $out = [];
        $assembled = $this->preparedMoulds()->get()->groupBy(fn ($m) => $m->mouldingRun->pattern_revision_id);
        foreach ($assembled as $revisionId => $moulds) {
            $out[$revisionId] = ['assembled' => $moulds->count(), 'unassembled' => 0, 'oldest' => $moulds->min(fn ($m) => $m->assembled_at ?? $m->created_at)];
        }
        foreach ($this->openRuns()->get() as $run) {
            $left = (int) $run->accepted_mould_qty - (int) $run->mould_assemblies_count;
            if ($left > 0) {
                $out[$run->pattern_revision_id] ??= ['assembled' => 0, 'unassembled' => 0, 'oldest' => null];
                $out[$run->pattern_revision_id]['unassembled'] += $left;
                $at = $run->completed_at ?? $run->created_at;
                $out[$run->pattern_revision_id]['oldest'] = $out[$run->pattern_revision_id]['oldest'] && $out[$run->pattern_revision_id]['oldest'] < $at ? $out[$run->pattern_revision_id]['oldest'] : $at;
            }
        }
        $revisions = PatternRevision::with('pattern')->whereIn('id', array_keys($out))->get()->keyBy('id');

        return collect($out)->filter(fn ($r, $id) => $revisions->has($id))
            ->map(fn ($r, $id) => $r + ['revision' => $revisions[$id], 'expected' => $r['assembled'] + $r['unassembled']])
            ->sortBy('oldest');
    }

    /**
     * Records the count of each pattern and writes off what is short.
     *
     * @param  array<string, int>  $counted  pattern revision id => moulds counted
     * @return array{counted: int, written_off: int}
     *
     * @throws InvalidArgumentException when moulds are short and no reason is given
     */
    public function record(array $counted, ?string $reason, ?string $note, ?string $heatId, ?string $by): array
    {
        $waiting = $this->waiting();
        $short = collect($counted)->filter(fn ($n, $id) => $waiting->has($id) && $n < $waiting[$id]['expected']);
        if ($short->isNotEmpty() && ! isset(MouldCount::REASONS[$reason])) {
            throw new InvalidArgumentException('Some patterns have fewer moulds than the records. Choose why, so the shortfall can be written off.');
        }

        return DB::transaction(function () use ($counted, $waiting, $reason, $note, $heatId, $by) {
            [$moulds, $off] = [0, 0];
            foreach ($counted as $revisionId => $n) {
                $expected = (int) ($waiting[$revisionId]['expected'] ?? 0);
                $lost = max(0, $expected - $n);
                if ($lost > 0) {
                    $this->writeOff($revisionId, $lost, MouldCount::REASONS[$reason].($note ? ': '.$note : ''));
                }
                MouldCount::create(['pattern_revision_id' => $revisionId, 'heat_id' => $heatId, 'expected' => $expected, 'counted' => $n, 'written_off' => $lost,
                    'reason' => $lost > 0 ? $reason : null, 'note' => $note, 'counted_by' => $by, 'counted_at' => now()]);
                [$moulds, $off] = [$moulds + $n, $off + $lost];
            }

            return ['counted' => $moulds, 'written_off' => $off];
        });
    }

    private function writeOff(string $revisionId, int $lost, string $why): void
    {
        foreach ($this->openRuns()->where('pattern_revision_id', $revisionId)->get() as $run) {
            $take = min($lost, (int) $run->accepted_mould_qty - (int) $run->mould_assemblies_count);
            if ($take > 0) {
                $this->lose($run, $take, $why);
                $lost -= $take;
            }
            if ($lost < 1) {
                return;
            }
        }
        foreach ($this->preparedMoulds()->whereHas('mouldingRun', fn ($q) => $q->where('pattern_revision_id', $revisionId))->limit($lost)->get() as $mould) {
            $mould->update(['status' => 'SCRAPPED']);
            $this->flasks->mouldStatusChanged($mould);
            $this->lose($mould->mouldingRun, 1, $why);
        }
    }

    /** Moves moulds from the run's accepted to its damaged ones, with the reason on the run. */
    private function lose(MouldingRun $run, int $moulds, string $why): void
    {
        $run->update([
            'accepted_mould_qty' => max(0, (int) $run->accepted_mould_qty - $moulds), 'damaged_mould_qty' => (int) $run->damaged_mould_qty + $moulds,
            'notes' => trim(($run->notes ?? '')."\nMould count: {$moulds} written off ({$why})."),
        ]);
    }

    private function preparedMoulds()
    {
        return MouldAssembly::with('mouldingRun')->where('status', 'PREPARED')->whereNotIn('id', PourMouldAssembly::select('mould_assembly_id'))
            ->whereHas('mouldingRun', fn ($q) => $q->where('status', '!=', 'CANCELLED'))->orderBy('assembled_at')->orderBy('created_at');
    }

    private function openRuns()
    {
        return MouldingRun::withCount(['mouldAssemblies' => fn ($q) => $q->where('status', '!=', 'SCRAPPED')])->where('status', '!=', 'CANCELLED')
            ->whereIn('production_order_id', ProductionOrder::whereIn('status', ['RELEASED', 'IN_PROGRESS'])->select('id'))
            ->orderBy('run_date')->orderBy('created_at');
    }
}
