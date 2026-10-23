<?php

namespace App\Services\Stock;

use App\Models\Material;
use App\Notifications\AppNotice;
use App\Services\Notifications\Notify;
use App\Support\Formats;
use Illuminate\Support\Facades\Route;

/**
 * Raises one reorder notice each time a watched material falls to its minimum, to everyone who manages stores, and
 * clears the flag when stock is back above the minimum so the next dip alerts again.
 */
class ReorderWatcher
{
    public const PERMISSION = 'stores.manage';

    public function __construct(private StockLevels $levels, private Notify $notify) {}

    /** Checks one material; returns true when a new notice was raised. */
    public function check(Material $material): bool
    {
        $onHand = $this->levels->onHand([$material->id])[$material->id] ?? 0.0;

        if (! $this->levels->isAtOrBelowMinimum($material, $onHand)) {
            if ($material->reorder_alerted_at !== null) {
                $material->forceFill(['reorder_alerted_at' => null])->save();
            }

            return false;
        }
        if ($material->reorder_alerted_at !== null) {
            return false;
        }

        $material->forceFill(['reorder_alerted_at' => now()])->save();
        $out = $onHand <= 0.0005;
        $this->notify->toPermission(self::PERMISSION, new AppNotice(
            'reorder',
            $out ? "{$material->name} is out of stock" : "{$material->name} is at its minimum",
            'On hand '.Formats::number($onHand, 3).' '.$material->base_uom.' against a minimum of '.Formats::number((float) $material->minimum_stock_qty, 3)
                .'. Suggested order: '.Formats::number($this->levels->suggestedOrder($material, $onHand), 3).' '.$material->base_uom.'.',
            $out ? 'danger' : 'warning',
            Route::has('stock-reorder.index') ? route('stock-reorder.index') : route('stock-report.index'),
        ));

        return true;
    }

    /** Checks every material that is watched or still flagged. Returns how many new notices were raised. */
    public function checkAll(): int
    {
        return Material::query()
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('is_stock_tracked', true)->where('minimum_stock_qty', '>', 0))->orWhereNotNull('reorder_alerted_at'))
            ->get()->filter(fn (Material $material) => $this->check($material))->count();
    }
}
