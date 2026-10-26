<?php

namespace App\Services\Production;

use App\Models\Flask;
use App\Models\FlaskSize;
use App\Models\MouldingStation;
use App\Models\MouldingWorkList;
use App\Models\MouldingWorkListItem;
use App\Models\PatternRevision;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Flask sets wanted by planned moulding against the sets free. Only moulds that keep their flasks until shakeout count: a mould
 * stripped at pour gives its set back the same shift. Sizes with no stock recorded (no flasks registered, no sets owned) are not checked.
 */
class FlaskLoad
{
    /** @var array<string, bool> */
    private array $tracked = [];

    public function __construct(private FlaskManager $flasks) {}

    /** Whether the stock of a size is known: kept by count, or with flasks registered. */
    public function tracksStock(FlaskSize $size): bool
    {
        return $this->tracked[$size->id] ??= ! $size->is_block && ($this->flasks->isCountOnly($size) || Flask::where('flask_size_id', $size->id)->exists());
    }

    /**
     * The size a pattern takes on a station when it holds its flasks to shakeout, or null when it does not tie up a counted set
     * (flaskless, stripped at pour, no size set, or a size whose stock is not recorded).
     */
    public function heldSize(PatternRevision $revision, ?MouldingStation $station, ?string $sand, ?string $override = null): ?FlaskSize
    {
        if ($this->flasks->isFlaskless($station, $revision) || ($override ?? $this->flasks->freedAt($revision->pattern, $sand)) === 'POUR') {
            return null;
        }
        $size = $this->flasks->sizesFor($revision, $station)->first();

        return $size && $this->tracksStock($size) ? $size : null;
    }

    /**
     * Per size: sets owned or registered, free now, wanted by planned rows of open lists (from a day on, or of one list), and short.
     *
     * @return Collection<int, array{size: FlaskSize, free: int, in_use: int, wanted: int, short: int}>
     */
    public function bySize(?CarbonInterface $from = null, ?MouldingWorkList $list = null): Collection
    {
        $items = MouldingWorkListItem::with(['patternRevision.pattern', 'station'])->where('status', 'PLANNED')->whereNull('moulding_run_id')
            ->when($list, fn ($q) => $q->where('moulding_work_list_id', $list->id))
            ->when(! $list, fn ($q) => $q->whereHas('workList', fn ($l) => $l->whereIn('status', ['DRAFT', 'ISSUED'])->when($from, fn ($l) => $l->whereDate('list_date', '>=', $from))))
            ->get();
        $wanted = [];
        foreach ($items as $item) {
            if ($size = $this->heldSize($item->patternRevision, $item->station, $item->sand_system, $item->flasks_freed)) {
                $wanted[$size->id] = ($wanted[$size->id] ?? 0) + (int) $item->planned_moulds;
            }
        }

        return FlaskSize::ordered()->where('status', 'ACTIVE')->get()->filter(fn ($size) => $this->tracksStock($size))
            ->when($list, fn ($sizes) => $sizes->filter(fn ($size) => isset($wanted[$size->id])))
            ->map(function (FlaskSize $size) use ($wanted) {
                $free = $this->flasks->freeSets($size);
                $want = $wanted[$size->id] ?? 0;

                return ['size' => $size, 'free' => $free, 'in_use' => $this->inUse($size), 'wanted' => $want, 'short' => max(0, $want - $free)];
            })->values();
    }

    /** Sets of a size in moulds or held by runs right now. */
    private function inUse(FlaskSize $size): int
    {
        if ($this->flasks->isCountOnly($size)) {
            return $this->flasks->setsInUse($size);
        }

        return intdiv(Flask::where('flask_size_id', $size->id)->where('status', 'IN_MOULD')->count(), 2);
    }
}
