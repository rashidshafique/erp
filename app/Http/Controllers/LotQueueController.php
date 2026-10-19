<?php

namespace App\Http\Controllers;

use App\Models\CastingLot;
use App\Models\PackingRecordItem;
use App\Services\Production\LotService;
use App\Services\Production\ShipmentService;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class LotQueueController extends Controller
{
    /** Where every open lot stands: waiting for shakeout, after which stage, ready to pack, packed. */
    public function queue(LotService $svc): View
    {
        $lots = CastingLot::with(['product', 'stageEvents.stage', 'pourMouldAssembly.pour', 'productionOrder'])
            ->whereIn('status', ['IN_PRODUCTION', 'ON_HOLD', 'READY'])->get();

        $buckets = [];
        foreach ($lots as $lot) {
            $last = $lot->stageEvents->sortBy(fn ($e) => $e->stage?->sequence)->last()?->stage;
            if ($lot->status === 'READY') {
                $key = '9 Ready to pack';
                $label = 'Ready to pack';
            } elseif ($last) {
                $key = sprintf('%03d', $last->sequence).' After '.$last->name;
                $label = 'After '.$last->name;
            } else {
                $key = '000 Waiting for shakeout';
                $label = 'Waiting for shakeout';
            }
            $poured = $lot->pourMouldAssembly?->pour?->completed_at;
            $cool = $svc->cooling($lot);
            $buckets[$key]['label'] = $label;
            $buckets[$key]['lots'][] = [
                'lot' => $lot, 'pieces' => $svc->pieces($lot), 'poured' => $poured, 'age_h' => $poured ? (int) $poured->diffInHours(now()) : null,
                'cool' => $cool, 'urgent' => $lot->productionOrder?->required_date,
            ];
        }
        ksort($buckets);
        foreach ($buckets as &$b) {
            usort($b['lots'], fn ($x, $y) => ($y['age_h'] ?? -1) <=> ($x['age_h'] ?? -1));
        }

        $awaitingDispatch = Route::has('finished-goods.index')
            ? PackingRecordItem::with(['lot.product', 'packing'])->get()->filter(fn ($i) => app(ShipmentService::class)->undispatched($i) > 0)->count()
            : 0;

        return view('lots.queue', ['buckets' => $buckets, 'awaitingDispatch' => $awaitingDispatch]);
    }
}
