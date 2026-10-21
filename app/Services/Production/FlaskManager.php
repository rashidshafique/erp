<?php

namespace App\Services\Production;

use App\Models\CastingLot;
use App\Models\Flask;
use App\Models\FlaskSize;
use App\Models\MouldAssembly;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\MouldLayer;
use App\Models\PatternRevision;
use App\Models\ToolingMaintenanceRecord;
use App\Models\ToolingUsageRecord;
use App\Support\FlaskRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Flasks through a mould's life: a set is claimed when moulds are made (bottom to top: drag, sections, cope), and given back
 * when the moulds are knocked out, which counts one use of each flask. Flaskless moulds (a flaskless station, or a vertically
 * parted pattern) never claim any. The rules (shortage, pairing, release, runs) are on the flask board.
 */
class FlaskManager
{
    /** Set when moulds were made with fewer flasks than they needed, under the "allow with a warning" rule. */
    public ?string $warning = null;

    public function isFlaskless(?MouldingStation $station, ?PatternRevision $revision): bool
    {
        return (bool) $station?->makes_flaskless || (bool) $revision?->isFlaskless();
    }

    /**
     * The sizes a pattern revision can be moulded in on a station: the sizes it fits that the station handles, the one it was
     * designed for first. A station that lists no sizes takes any.
     *
     * @return Collection<int, FlaskSize>
     */
    public function sizesFor(PatternRevision $revision, ?MouldingStation $station): Collection
    {
        $sizes = $revision->flaskSizes()->where('flask_sizes.status', 'ACTIVE')->get();
        $handled = $station ? $station->flaskSizes()->pluck('flask_sizes.id') : collect();

        return $handled->isEmpty() ? $sizes : $sizes->whereIn('id', $handled->all())->values();
    }

    /** Layer types of a mould with $sections flask sections, bottom to top. */
    public function layerTypes(int $sections): array
    {
        return $sections <= 1 ? ['DRAG'] : array_merge(['DRAG'], array_fill(0, $sections - 2, 'STACK'), ['COPE']);
    }

    /**
     * Picks a set of free flasks for each of $moulds moulds, in the first size that can make them all (the designed-for size
     * first), else in the size that can make the most. Flasks used longest ago go first.
     *
     * @return array{size: ?FlaskSize, sets: array<int, array<int, Flask>>, short: int}
     */
    public function pick(Collection $sizes, int $moulds, array $types, bool $lock = false): array
    {
        $best = ['size' => null, 'sets' => [], 'short' => $moulds];
        foreach ($sizes as $size) {
            $pool = Flask::where('flask_size_id', $size->id)->where('status', 'AVAILABLE')
                ->orderByRaw('last_used_at asc nulls first')->orderBy('code')
                ->when($lock, fn ($q) => $q->lockForUpdate())->get();
            $sets = [];
            $used = [];
            for ($m = 0; $m < $moulds; $m++) {
                $set = $this->pickSet($pool, $types, $used);
                if (! $set) {
                    break;
                }
                foreach ($set as $flask) {
                    $used[$flask->id] = true;
                }
                $sets[] = $set;
            }
            if (count($sets) > count($best['sets'])) {
                $best = ['size' => $size, 'sets' => $sets, 'short' => $moulds - count($sets)];
            }
            if (count($sets) === $moulds) {
                break;
            }
        }

        return $best;
    }

    /** @return array<int, Flask>|null one flask per layer type, or null when the pool cannot make a set */
    private function pickSet(Collection $pool, array $types, array $used): ?array
    {
        $fixed = FlaskRules::get('pairing') === 'FIXED';
        $free = fn (string $type, array $taken) => $pool
            ->filter(fn (Flask $f) => ! isset($used[$f->id]) && ! isset($taken[$f->id]) && $f->fits($type))
            ->sortBy(fn (Flask $f) => $f->position === $type ? 0 : 1)->values();
        if (FlaskRules::get('pairing') === 'MIXED' && count($types) > 1) {
            return $this->pickMixedSet($pool, $types, $used, $free);
        }
        $set = [];
        $taken = [];
        $last = count($types) - 1;
        foreach ($types as $i => $type) {
            if ($fixed && $i === $last && $last > 0) {
                // The cope must be the drag's own partner.
                $partner = $pool->firstWhere('id', $set[0]->partner_flask_id);
                if (! $partner || isset($used[$partner->id]) || isset($taken[$partner->id]) || ! $partner->fits($type)) {
                    return null;
                }
                $set[$i] = $partner;
                $taken[$partner->id] = true;

                continue;
            }
            $candidates = $free($type, $taken);
            if ($fixed && $i === 0 && $last > 0) {
                $candidates = $candidates->filter(fn (Flask $f) => $f->partner_flask_id && $pool->contains('id', $f->partner_flask_id) && ! isset($used[$f->partner_flask_id]))->values();
            }
            $flask = $candidates->first();
            if (! $flask) {
                return null;
            }
            $set[$i] = $flask;
            $taken[$flask->id] = true;
        }

        return $set;
    }

    /**
     * A set when pairs and interchangeable flasks live side by side: a drag with a partner is closed by that partner,
     * a drag without one by a cope that has none. The cope is chosen before any sections in between, so a section
     * cannot take the flask a pair is waiting for, and sections prefer flasks that have no partner.
     *
     * @param  \Closure(string, array<string, bool>): Collection<int, Flask>  $free
     * @return array<int, Flask>|null
     */
    private function pickMixedSet(Collection $pool, array $types, array $used, \Closure $free): ?array
    {
        $last = count($types) - 1;
        foreach ($free($types[0], []) as $drag) {
            $taken = [$drag->id => true];
            if ($drag->partner_flask_id) {
                $cope = $pool->firstWhere('id', $drag->partner_flask_id);
                if (! $cope || isset($used[$cope->id]) || ! $cope->fits($types[$last])) {
                    continue;
                }
            } else {
                $cope = $free($types[$last], $taken)->first(fn (Flask $f) => ! $f->partner_flask_id);
                if (! $cope) {
                    continue;
                }
            }
            $set = [0 => $drag, $last => $cope];
            $taken[$cope->id] = true;
            for ($i = 1; $i < $last; $i++) {
                $section = $free($types[$i], $taken)->sortBy(fn (Flask $f) => $f->partner_flask_id ? 1 : 0)->first();
                if (! $section) {
                    continue 2;
                }
                $set[$i] = $section;
                $taken[$section->id] = true;
            }
            ksort($set);

            return $set;
        }

        return null;
    }

    /**
     * Gives freshly assembled moulds their flasks, layer by layer. Called inside the assembly transaction.
     *
     * @param  Collection<int, MouldAssembly>  $moulds
     */
    public function assignToMoulds(MouldingRun $run, Collection $moulds): void
    {
        $this->warning = null;
        $revision = $run->patternRevision;
        if ($this->isFlaskless($run->station, $revision)) {
            MouldAssembly::whereIn('id', $moulds->pluck('id'))->update(['is_flaskless' => true]);

            return;
        }
        if (! $revision->flaskSizes()->exists()) {
            // Flasks are tracked pattern by pattern, once the sizes it fits are set; until then moulds are made without them.
            $this->warning = "No flask size is set for {$revision->code}, so no flasks were recorded for these moulds. Set the sizes it fits on the pattern to track them.";

            return;
        }
        $layers = MouldLayer::whereIn('mould_assembly_id', $moulds->pluck('id'))->orderBy('layer_number')->get()->groupBy('mould_assembly_id');
        $types = $layers->first()?->pluck('layer_type')->all() ?? [];
        $picked = $this->pick($this->sizesFor($revision, $run->station), $moulds->count(), $types, lock: true);
        if ($picked['short'] > 0) {
            $this->shortage($revision, $run->station, $types, $moulds->count(), count($picked['sets']));
        }
        foreach ($moulds->values() as $i => $mould) {
            $set = $picked['sets'][$i] ?? null;
            if (! $set) {
                continue;
            }
            $mould->update(['flask_size_id' => $picked['size']->id]);
            foreach ($layers[$mould->id] as $j => $layer) {
                $layer->update(['flask_id' => $set[$j]->id]);
                $set[$j]->update(['status' => 'IN_MOULD', 'current_mould_assembly_id' => $mould->id]);
            }
        }
    }

    /** Blocks, or records a warning, when there were not enough flasks; the message says what is free. */
    private function shortage(PatternRevision $revision, ?MouldingStation $station, array $types, int $wanted, int $got): void
    {
        $sizes = $this->sizesFor($revision, $station);
        $need = implode(' + ', array_map(fn ($t) => strtolower($t), $types));
        if ($sizes->isEmpty()) {
            $message = "No flask size is set for {$revision->code}".($station ? " that station {$station->code} handles" : '').'. Set the sizes it fits on the pattern, and the sizes the station handles.';
        } else {
            $free = $sizes->map(function (FlaskSize $size) {
                $counts = Flask::where('flask_size_id', $size->id)->where('status', 'AVAILABLE')->selectRaw('position, count(*) n')->groupBy('position')->pluck('n', 'position');

                return $size->name.': '.($counts->isEmpty() ? 'none free' : $counts->map(fn ($n, $p) => $n.' '.strtolower($p))->implode(', ').' free');
            })->implode('; ');
            $message = "{$wanted} ".($wanted === 1 ? 'mould needs' : 'moulds need')." a set of flasks ({$need}), but only {$got} ".($got === 1 ? 'set is' : 'sets are')." free. {$free}.";
        }
        if (FlaskRules::get('shortage') === 'BLOCK') {
            throw new InvalidArgumentException($message.' Release the flasks of knocked-out moulds, or change the rule on the flask board.');
        }
        $this->warning = $message.' The moulds without a set were made with no flask recorded.';
    }

    /** Gives back the flasks of a mould. Each counts one use. */
    public function releaseMould(MouldAssembly $mould): int
    {
        $flasks = Flask::where('current_mould_assembly_id', $mould->id)->lockForUpdate()->get();
        foreach ($flasks as $flask) {
            $this->giveBack($flask, 'MOULD_ASSEMBLY', $mould->id, "Mould {$mould->assembly_number} knocked out.");
        }

        return $flasks->count();
    }

    /** Follows a mould's status: scrapped and cancelled moulds give their flasks back, poured ones too under the "at pour" rule. */
    public function mouldStatusChanged(MouldAssembly $mould): void
    {
        if (in_array($mould->status, ['SCRAPPED', 'CANCELLED'], true)
            || ($mould->status === 'POURED' && FlaskRules::get('release') === 'POUR')) {
            $this->releaseMould($mould);
        }
    }

    /** Shakeout of a lot gives back the flasks of the mould its castings came from, under the "at shakeout" rule. */
    public function shakenOut(CastingLot $lot): int
    {
        if (FlaskRules::get('release') !== 'SHAKEOUT') {
            return 0;
        }

        return $lot->mouldAssemblies()->sum(fn (MouldAssembly $mould) => $this->releaseMould($mould));
    }

    private function giveBack(Flask $flask, string $referenceType, string $referenceId, string $note): void
    {
        $flask->update(['status' => 'AVAILABLE', 'current_mould_assembly_id' => null, 'current_moulding_run_id' => null, 'cycles' => $flask->cycles + 1, 'last_used_at' => now()]);
        ToolingUsageRecord::create([
            'flask_id' => $flask->id, 'tooling_type' => 'FLASK', 'usage_quantity' => 1, 'usage_unit' => 'MOULDS', 'used_at' => now(),
            'reference_type' => $referenceType, 'reference_id' => $referenceId, 'recorded_by' => auth()->user()?->name, 'notes' => $note,
        ]);
    }

    /**
     * Puts another flask into a mould layer, for when the floor used a different one than proposed. The flask taken out goes
     * back to the rack without counting a use.
     */
    public function swap(MouldLayer $layer, Flask $flask): void
    {
        $mould = $layer->assembly ?? MouldAssembly::findOrFail($layer->mould_assembly_id);
        if ($flask->id === $layer->flask_id) {
            return;
        }
        if ($flask->status !== 'AVAILABLE') {
            throw ValidationException::withMessages(['flask' => "{$flask->label()} is ".strtolower(Flask::STATUSES[$flask->status]).', not available.']);
        }
        if ($mould->flask_size_id && $flask->flask_size_id !== $mould->flask_size_id) {
            throw ValidationException::withMessages(['flask' => "{$flask->label()} is a {$flask->size->name} flask; this mould is in {$mould->flaskSize->name} flasks."]);
        }
        if (! $flask->fits($layer->layer_type)) {
            throw ValidationException::withMessages(['flask' => "{$flask->label()} is a ".strtolower(Flask::POSITIONS[$flask->position]).' flask and cannot be the '.strtolower($layer->layer_type).' of this mould.']);
        }
        $pairing = FlaskRules::get('pairing');
        if (in_array($pairing, ['FIXED', 'MIXED'], true) && in_array($layer->layer_type, ['DRAG', 'COPE'], true)) {
            $other = MouldLayer::where('mould_assembly_id', $mould->id)->where('layer_type', $layer->layer_type === 'DRAG' ? 'COPE' : 'DRAG')->value('flask_id');
            if ($other && $flask->partner_flask_id !== $other) {
                if ($pairing === 'FIXED') {
                    throw ValidationException::withMessages(['flask' => "Flasks go in fixed pairs: {$flask->label()} is not the partner of the other flask in this mould."]);
                }
                if ($flask->partner_flask_id || Flask::whereKey($other)->value('partner_flask_id')) {
                    throw ValidationException::withMessages(['flask' => "A flask with a partner goes only with it: {$flask->label()} is not the partner of the other flask in this mould."]);
                }
            }
        }
        $old = $layer->flask_id ? Flask::find($layer->flask_id) : null;
        $old?->update(['status' => 'AVAILABLE', 'current_mould_assembly_id' => null]);
        $layer->update(['flask_id' => $flask->id]);
        $flask->update(['status' => 'IN_MOULD', 'current_mould_assembly_id' => $mould->id]);
        $mould->flask_size_id ?: $mould->update(['flask_size_id' => $flask->flask_size_id]);
    }

    /** Takes a flask out of service for repair, with a maintenance record describing the problem. */
    public function sendToRepair(Flask $flask, string $problem): ToolingMaintenanceRecord
    {
        if ($flask->status === 'IN_MOULD') {
            throw ValidationException::withMessages(['problem' => "{$flask->label()} is in a mould. Release it first."]);
        }
        $flask->update(['status' => 'REPAIR']);

        return ToolingMaintenanceRecord::create([
            'flask_id' => $flask->id, 'tooling_type' => 'FLASK', 'record_number' => 'TM-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'activity_type' => 'REPAIR', 'status' => 'OPEN', 'priority' => 'NORMAL', 'reported_at' => now(), 'reported_by' => auth()->user()?->name,
            'problem_description' => $problem,
        ]);
    }

    /**
     * Under the "runs claim by count" rule, a run without assembled moulds holds one set of flasks for each mould made. Called
     * when a run's moulds made change; surplus sets go back without counting a use.
     */
    public function runChanged(MouldingRun $run): void
    {
        if (FlaskRules::get('run_claims') !== 'COUNT' || ! $run->pattern_revision_id || $run->mouldAssemblies()->exists()
            || $this->isFlaskless($run->station, $run->patternRevision) || ! $run->patternRevision->flaskSizes()->exists()) {
            return;
        }
        $types = $this->layerTypes((int) ($run->patternRevision->layers_per_mould ?: 2));
        $held = Flask::where('current_moulding_run_id', $run->id)->lockForUpdate()->get();
        $sets = intdiv($held->count(), count($types));
        $want = max(0, (int) $run->moulds_made_qty);
        if ($want < $sets) {
            foreach ($held->take(($sets - $want) * count($types)) as $flask) {
                $flask->update(['status' => 'AVAILABLE', 'current_moulding_run_id' => null]);
            }

            return;
        }
        if ($want === $sets) {
            return;
        }
        $picked = $this->pick($this->sizesFor($run->patternRevision, $run->station), $want - $sets, $types, lock: true);
        if ($picked['short'] > 0) {
            try {
                $this->shortage($run->patternRevision, $run->station, $types, $want - $sets, count($picked['sets']));
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['flasks' => "Run {$run->run_number}: ".$e->getMessage()]);
            }
        }
        foreach ($picked['sets'] as $set) {
            foreach ($set as $flask) {
                $flask->update(['status' => 'IN_MOULD', 'current_moulding_run_id' => $run->id]);
            }
        }
    }

    /** Gives back the flasks a run holds by count, counting one use each. */
    public function releaseRun(MouldingRun $run): int
    {
        $flasks = Flask::where('current_moulding_run_id', $run->id)->lockForUpdate()->get();
        foreach ($flasks as $flask) {
            $this->giveBack($flask, 'MOULDING_RUN', $run->id, "Run {$run->run_number} knocked out.");
        }

        return $flasks->count();
    }
}
