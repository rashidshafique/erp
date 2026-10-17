<?php

namespace App\Services\Production;

use App\Models\MouldAssembly;
use App\Models\MouldCavity;
use App\Models\MouldingRun;
use App\Models\MouldLayer;
use App\Models\PatternRevision;
use App\Models\PatternRevisionCavity;
use App\Services\PatternCavityMap;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Turns accepted moulds of a moulding run into mould assemblies, with their cavities taken from the pattern's cavity map.
 * Castings form at the joints between flask sections: a cope-and-drag mould (2 sections) holds the layout once, a stack of
 * N sections up to N − 1 times, never more than the casting levels of the pattern revision.
 */
class MouldAssembler
{
    public function __construct(private PatternCavityMap $map) {}

    /** @return Collection<int, MouldAssembly> */
    public function assemble(MouldingRun $run, int $count, string $configurationId, int $layers = 2, ?string $assembledBy = null, ?string $checkedBy = null): Collection
    {
        if ($run->status === 'CANCELLED') {
            throw new InvalidArgumentException('A cancelled moulding run cannot be assembled.');
        }
        if (! $run->pattern_revision_id || ! $this->map->hasMap($run->pattern_revision_id)) {
            throw new InvalidArgumentException('Set a pattern revision with a cavity map on the moulding run first, so the cavities and their products are known.');
        }
        if ($count < 1 || $count > 500) {
            throw new InvalidArgumentException('Assemble between 1 and 500 moulds at a time.');
        }
        $cavities = PatternRevisionCavity::where('pattern_revision_id', $run->pattern_revision_id)->orderBy('cavity_number')->get();
        $types = $layers <= 1 ? ['DRAG'] : array_merge(['DRAG'], array_fill(0, $layers - 2, 'STACK'), ['COPE']);
        $levels = min(PatternRevision::findOrFail($run->pattern_revision_id)->castingLevels(), max(1, $layers - 1));

        return DB::transaction(function () use ($run, $count, $configurationId, $assembledBy, $checkedBy, $cavities, $types, $levels) {
            MouldingRun::whereKey($run->id)->lockForUpdate()->first();
            $existing = MouldAssembly::where('moulding_run_id', $run->id)->count();
            if ((int) $run->accepted_mould_qty < $existing + $count) {
                throw new InvalidArgumentException("The run has {$run->accepted_mould_qty} accepted moulds and {$existing} are already assembled, so {$count} more would exceed it.");
            }

            $date = now()->format('Ymd');
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['assembly-number-'.$date]);
            $prefix = "MA-{$date}-";
            $last = MouldAssembly::where('assembly_number', 'like', $prefix.'%')->pluck('assembly_number')
                ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

            $made = collect();
            for ($i = 1; $i <= $count; $i++) {
                $asm = MouldAssembly::create([
                    'assembly_number' => $prefix.str_pad((string) ($last + $i), 3, '0', STR_PAD_LEFT),
                    'moulding_run_id' => $run->id,
                    'mould_configuration_id' => $configurationId,
                    'status' => 'PREPARED',
                    'assembled_at' => now(),
                    'assembled_by' => $assembledBy,
                    'checked_by' => $checkedBy,
                ]);
                foreach ($types as $n => $type) {
                    $layer = MouldLayer::create(['mould_assembly_id' => $asm->id, 'layer_number' => $n + 1, 'layer_type' => $type]);
                    if ($n >= $levels) {
                        continue;   // the top flask closes the mould; it holds no layout of its own
                    }
                    foreach ($cavities as $c) {
                        MouldCavity::create([
                            'mould_layer_id' => $layer->id,
                            'cavity_number' => $c->cavity_number,
                            'pattern_revision_id' => $run->pattern_revision_id,
                            'product_revision_id' => $c->product_revision_id,
                            'nominal_casting_weight_kg' => $c->casting_weight_kg,
                            'castings_per_cavity' => 1,
                            'is_active' => true,
                        ]);
                    }
                }
                $made->push($asm);
            }

            return $made;
        });
    }
}
