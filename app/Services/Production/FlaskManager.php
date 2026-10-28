<?php

namespace App\Services\Production;

use App\Models\CastingLot;
use App\Models\Flask;
use App\Models\FlaskSize;
use App\Models\MouldAssembly;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\MouldLayer;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\SandSystem;
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
     * designed for first. A station that lists no sizes takes any. A flaskless mould is made in a block size, any other in a
     * flask size, and a station's list does not narrow the block sizes.
     *
     * @return Collection<int, FlaskSize>
     */
    public function sizesFor(PatternRevision $revision, ?MouldingStation $station): Collection
    {
        $flaskless = $this->isFlaskless($station, $revision);
        $sizes = $revision->flaskSizes()->where('flask_sizes.status', 'ACTIVE')->where('flask_sizes.is_block', $flaskless)->get();
        $handled = $station && ! $flaskless ? $station->flaskSizes()->pluck('flask_sizes.id') : collect();

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
            MouldAssembly::whereIn('id', $moulds->pluck('id'))->update(['is_flaskless' => true, 'flask_size_id' => $this->sizesFor($revision, $run->station)->first()?->id]);

            return;
        }
        if (! $revision->flaskSizes()->exists()) {
            // Flasks are tracked pattern by pattern, once the sizes it fits are set; until then moulds are made without them.
            $this->warning = "No flask size is set for {$revision->code}, so no flasks were recorded for these moulds. Set the sizes it fits on the pattern to track them.";

            return;
        }
        $layers = MouldLayer::whereIn('mould_assembly_id', $moulds->pluck('id'))->orderBy('layer_number')->get()->groupBy('mould_assembly_id');
        $types = $layers->first()?->pluck('layer_type')->all() ?? [];
        $sizes = $this->sizesFor($revision, $run->station);
        if ($sizes->isNotEmpty() && $sizes->every(fn (FlaskSize $size) => $this->isCountOnly($size))) {
            $counted = $this->pickCounted($sizes, $moulds->count());
            if ($counted['short'] > 0) {
                $this->shortage($revision, $run->station, $types, $moulds->count(), $counted['sets']);
            }
            MouldAssembly::whereIn('id', $moulds->values()->take($counted['sets'])->pluck('id'))->update(['flask_size_id' => $counted['size']->id, 'holds_flask_set' => true]);

            return;
        }
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
                if ($this->isCountOnly($size)) {
                    return "{$size->name}: {$this->freeSets($size)} of {$size->sets_owned} sets free";
                }
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
        // A counted set has no flask records: the mould just stops holding it.
        $counted = MouldAssembly::whereKey($mould->id)->where('holds_flask_set', true)->update(['holds_flask_set' => false]);

        return $flasks->count() + $counted * max(1, $mould->layers()->count());
    }

    /** A size kept by count: it has a number of sets owned and no flask of it is registered. */
    public function isCountOnly(FlaskSize $size): bool
    {
        return $size->sets_owned !== null && ! $size->is_block && ! Flask::where('flask_size_id', $size->id)->exists();
    }

    /** Counted sets of a size in moulds or held by runs. */
    public function setsInUse(FlaskSize $size): int
    {
        return MouldAssembly::where('flask_size_id', $size->id)->where('holds_flask_set', true)->count()
            + (int) MouldingRun::where('flask_size_id', $size->id)->sum('flask_sets_held');
    }

    /**
     * Free sets of a size: owned less in use for a counted size; for registered flasks, the sets the free ones make up
     * for a $types mould (cope and drag by default).
     */
    public function freeSets(FlaskSize $size, array $types = ['DRAG', 'COPE']): int
    {
        if ($this->isCountOnly($size)) {
            return max(0, (int) $size->sets_owned - $this->setsInUse($size));
        }
        $free = Flask::where('flask_size_id', $size->id)->where('status', 'AVAILABLE')->count();

        return $free ? count($this->pick(collect([$size]), intdiv($free, max(1, count($types))), $types)['sets']) : 0;
    }

    /**
     * Counted sets for $moulds moulds, in the first count-only size with enough free (the designed-for size first), else the one
     * with the most. Serialised per size so two runs cannot take the same sets.
     *
     * @param  Collection<int, FlaskSize>  $sizes
     * @return array{size: ?FlaskSize, sets: int, short: int}
     */
    private function pickCounted(Collection $sizes, int $moulds): array
    {
        $best = ['size' => null, 'sets' => 0, 'short' => $moulds];
        foreach ($sizes as $size) {
            \Illuminate\Support\Facades\DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['flask-sets-'.$size->id]);
            $got = min($moulds, $this->freeSets($size));
            if ($got > $best['sets'] || ! $best['size']) {
                $best = ['size' => $size, 'sets' => $got, 'short' => $moulds - $got];
            }
            if ($got === $moulds) {
                break;
            }
        }

        return $best;
    }

    public const FREED = ['POUR' => 'At pour (flask stripped)', 'SHAKEOUT' => 'At shakeout'];

    /**
     * When flasks are freed for a pattern moulded in a sand: the pattern's own setting, else the sand system's, else the flask
     * board rule (which may also be "only by Release flasks").
     */
    public function freedAt(?Pattern $pattern, ?string $sand): string
    {
        return $this->patternDefault($pattern, $sand)[0];
    }

    /** When a mould's flasks are freed: set on the mould at the pour, else its run, else its work list row, else the pattern and sand. */
    public function freedFor(MouldAssembly $mould): string
    {
        return $mould->flasks_freed ?? $this->mouldDefault($mould)[0];
    }

    /** @return array{0: string, 1: string} what applies to a pattern in a sand, and where that comes from */
    public function patternDefault(?Pattern $pattern, ?string $sand): array
    {
        if ($pattern?->flasks_freed) {
            return [$pattern->flasks_freed, 'pattern'];
        }
        $bySand = $sand ? SandSystem::where('name', $sand)->value('flasks_freed') : null;

        return $bySand ? [$bySand, $sand] : [FlaskRules::get('release'), 'flask board'];
    }

    /** @return array{0: string, 1: string} what applies to a run when it sets nothing itself */
    public function runDefault(MouldingRun $run): array
    {
        $item = $run->workListItem;

        return $item?->flasks_freed ? [$item->flasks_freed, 'work list'] : $this->patternDefault($run->patternRevision?->pattern, $run->sand_system);
    }

    /** @return array{0: string, 1: string} what applies to a mould when it sets nothing itself */
    public function mouldDefault(MouldAssembly $mould): array
    {
        $run = $mould->mouldingRun;
        if (! $run) {
            return [FlaskRules::get('release'), 'flask board'];
        }

        return $run->flasks_freed ? [$run->flasks_freed, 'run'] : $this->runDefault($run);
    }

    /** A short label for a freed-at value, for the default choice of a select. */
    public static function freedLabel(string $value): string
    {
        return self::FREED[$value] ?? 'Only by Release flasks';
    }

    /** Follows a mould's status: scrapped and cancelled moulds give their flasks back, poured ones too when they are freed at pour. */
    public function mouldStatusChanged(MouldAssembly $mould): void
    {
        if (in_array($mould->status, ['SCRAPPED', 'CANCELLED'], true)
            || ($mould->status === 'POURED' && $this->freedFor($mould) === 'POUR')) {
            $this->releaseMould($mould);
        }
    }

    /** Shakeout of a lot gives back the flasks still held by the moulds its castings came from, unless they are released only by hand. */
    public function shakenOut(CastingLot $lot): int
    {
        return $lot->mouldAssemblies()->sum(fn (MouldAssembly $mould) => $this->freedFor($mould) === 'MANUAL' ? 0 : $this->releaseMould($mould));
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
            'flask_id' => $flask->id, 'tooling_type' => 'FLASK', 'record_number' => 'TM-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
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
        $sizes = $this->sizesFor($run->patternRevision, $run->station);
        if ($sizes->isNotEmpty() && $sizes->every(fn (FlaskSize $size) => $this->isCountOnly($size))) {
            $this->runChangedCounted($run, $sizes, $types);

            return;
        }
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
        $sets = (int) MouldingRun::whereKey($run->id)->value('flask_sets_held');
        if ($sets > 0) {
            MouldingRun::whereKey($run->id)->update(['flask_sets_held' => 0]);
        }

        return $flasks->count() + $sets * count($this->layerTypes((int) ($run->patternRevision?->layers_per_mould ?: 2)));
    }

    /**
     * A run on count-only sizes holds one counted set per mould made: more are taken as it makes more, surplus goes back.
     *
     * @param  Collection<int, FlaskSize>  $sizes
     */
    private function runChangedCounted(MouldingRun $run, Collection $sizes, array $types): void
    {
        $held = (int) MouldingRun::whereKey($run->id)->lockForUpdate()->value('flask_sets_held');
        $want = max(0, (int) $run->moulds_made_qty);
        if ($want <= $held) {
            MouldingRun::whereKey($run->id)->update(['flask_sets_held' => $want]);

            return;
        }
        $size = $held > 0 && $run->flask_size_id ? $sizes->firstWhere('id', $run->flask_size_id) : null;
        $counted = $this->pickCounted($size ? collect([$size]) : $sizes, $want - $held);
        if ($counted['short'] > 0) {
            try {
                $this->shortage($run->patternRevision, $run->station, $types, $want - $held, $counted['sets']);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['flasks' => "Run {$run->run_number}: ".$e->getMessage()]);
            }
        }
        MouldingRun::whereKey($run->id)->update(['flask_sets_held' => $held + $counted['sets'], 'flask_size_id' => $counted['size']?->id ?? $run->flask_size_id]);
    }
}
