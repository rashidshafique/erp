<?php

namespace App\Domains\QuickFlow\Services;

use App\Domains\QuickFlow\Models\QuickFloorEntry;
use App\Models\MouldingRun;
use App\Models\PatternRevision;
use App\Models\ProductionOrder;
use App\Services\PatternCavityMap;
use App\Services\Production\WorkListService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ties floor entries to production orders. An entry made before its order exists waits unlinked; it is linked by hand, or
 * automatically when a matching order opens. Linking a "made" entry writes its moulding run, which needs an order.
 */
class QuickFloorLinker
{
    public const OPEN = ['RELEASED', 'IN_PROGRESS'];

    /** Set while waiting entries are being linked: starting the order on the first link must not start another round. */
    private static bool $linking = false;

    public function __construct(private WorkListService $workLists, private PatternCavityMap $cavityMap) {}

    /** The products a pattern revision makes, by its cavity map. */
    public function productsOf(?PatternRevision $revision): Collection
    {
        if (! $revision) {
            return collect();
        }
        $cavities = $revision->relationLoaded('cavityMap') ? $revision->cavityMap->loadMissing('productRevision') : $revision->cavityMap()->with('productRevision')->get();

        return $cavities->map(fn ($c) => $c->productRevision?->product_id)->filter()->unique()->values();
    }

    /** The open order moulds of this revision go to by default: the best order still needing what it makes. */
    public function makeOrderFor(?PatternRevision $revision): ?ProductionOrder
    {
        return $revision ? $this->workLists->openOrdersFor($revision->id)->first() : null;
    }

    /**
     * The open order poured moulds of this revision go to by default: the first with moulds waiting to be poured, else any
     * open order for what it makes.
     *
     * @param  Collection<int, array<string, mixed>>  $planOrders  from NextHeatPlan::orders()
     */
    public function pourOrderFor(?PatternRevision $revision, Collection $planOrders): ?ProductionOrder
    {
        $products = $this->productsOf($revision);
        if ($products->isEmpty()) {
            return null;
        }

        return $planOrders->first(fn ($o) => $o['awaiting'] > 0 && $products->contains($o['order']->product_id))['order']
            ?? ProductionOrder::whereIn('status', self::OPEN)->whereIn('product_id', $products)->orderBy('required_date')->orderBy('production_order_number')->first();
    }

    /** Pieces of a product one mould of the revision gives; without a product, of the one product it makes. 1 when the map does not say. */
    public function piecesPerMould(?PatternRevision $revision, ?string $productId): int
    {
        if (! $revision) {
            return 1;
        }
        if ($productId === null) {
            $products = $this->productsOf($revision);
            $productId = $products->count() === 1 ? $products->first() : null;
        }

        return $productId ? max(1, (int) round($this->cavityMap->expectedProductsPerMould($revision, $productId))) : 1;
    }

    /**
     * Links an entry to an open order. The pieces per mould follow the order's product on the cavity map, unless the count was
     * typed with the entry ($keepPiecesPerMould); a "made" entry gets
     * its completed moulding run, and a released order starts. A refusal (such as a customer's pattern on another customer's
     * order) is a validation error and changes nothing.
     */
    public function link(QuickFloorEntry $entry, ProductionOrder $order, bool $keepPiecesPerMould = false): void
    {
        // Starting the order below saves it; that must not start an automatic round that links this same entry again.
        $outer = self::$linking;
        self::$linking = true;
        try {
            $this->linkNow($entry, $order, $keepPiecesPerMould);
        } finally {
            self::$linking = $outer;
        }
    }

    private function linkNow(QuickFloorEntry $entry, ProductionOrder $order, bool $keepPiecesPerMould): void
    {
        DB::transaction(function () use ($entry, $order, $keepPiecesPerMould) {
            $entry = QuickFloorEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $order = ProductionOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($entry->production_order_id !== null) {
                throw ValidationException::withMessages(['production_order_id' => 'This entry is already linked to an order.']);
            }
            if (! in_array($order->status, self::OPEN, true)) {
                throw ValidationException::withMessages(['production_order_id' => "Order {$order->production_order_number} is not open."]);
            }
            $revision = $entry->pattern_revision_id ? PatternRevision::find($entry->pattern_revision_id) : null;
            $perMould = $revision && ! $keepPiecesPerMould ? $this->cavityMap->expectedProductsPerMould($revision, $order->product_id) : 0;
            $entry->forceFill(['production_order_id' => $order->id, 'pieces_per_mould' => $perMould > 0 ? max(1, (int) round($perMould)) : $entry->pieces_per_mould]);

            if ($entry->kind === QuickFloorEntry::MADE) {
                $run = MouldingRun::create([
                    'run_number' => MouldingRun::nextRunNumber(), 'production_order_id' => $order->id, 'run_date' => $entry->entry_date,
                    'pattern_revision_id' => $entry->pattern_revision_id, 'moulding_station_id' => $entry->moulding_station_id,
                    'operator_name' => $entry->entered_by, 'planned_mould_qty' => $entry->moulds, 'moulds_made_qty' => $entry->moulds,
                    'accepted_mould_qty' => $entry->moulds, 'status' => 'COMPLETED', 'completed_at' => now(), 'notes' => $entry->notes,
                ]);
                $entry->moulding_run_id = $run->id;
                if ($order->status === 'RELEASED') {
                    $order->update(['status' => 'IN_PROGRESS', 'actual_started_at' => now()]);
                }
            }
            $entry->save();
        });
    }

    /**
     * Links entries waiting for an order to a newly open one: those whose pattern makes its product, made before poured, oldest
     * first, while the order still needs pieces of that kind. Entries the order cannot take are left waiting.
     */
    public function linkWaitingFor(ProductionOrder $order): int
    {
        if (self::$linking || ! in_array($order->status, self::OPEN, true)) {
            return 0;
        }
        self::$linking = true;
        try {
            return $this->linkWaiting($order);
        } finally {
            self::$linking = false;
        }
    }

    private function linkWaiting(ProductionOrder $order): int
    {
        $revisionIds = DB::table('pattern_revision_cavities')->join('product_revisions', 'product_revisions.id', '=', 'pattern_revision_cavities.product_revision_id')
            ->where('product_revisions.product_id', $order->product_id)->distinct()->pluck('pattern_revision_cavities.pattern_revision_id');
        if ($revisionIds->isEmpty()) {
            return 0;
        }
        $planned = (int) round((float) $order->planned_qty);
        $need = [];
        foreach ([QuickFloorEntry::MADE, QuickFloorEntry::POURED] as $kind) {
            $need[$kind] = $planned - (int) QuickFloorEntry::where('production_order_id', $order->id)->where('kind', $kind)->sum(DB::raw('moulds * pieces_per_mould'));
        }

        $linked = 0;
        $waiting = QuickFloorEntry::unlinked()->whereIn('pattern_revision_id', $revisionIds)
            ->orderByRaw("case when kind = 'MADE' then 0 else 1 end")->orderBy('entry_date')->orderBy('created_at')->get();
        foreach ($waiting as $entry) {
            if (($need[$entry->kind] ?? 0) <= 0) {
                continue;
            }
            try {
                $this->link($entry, $order);
            } catch (ValidationException) {
                continue;
            }
            $need[$entry->kind] -= $entry->fresh()->pieces();
            $linked++;
        }

        return $linked;
    }
}
