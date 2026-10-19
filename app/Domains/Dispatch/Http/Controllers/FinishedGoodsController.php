<?php

namespace App\Domains\Dispatch\Http\Controllers;

use App\Domains\Dispatch\Models\PackingRecordItem;
use App\Domains\Dispatch\Services\ShipmentService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class FinishedGoodsController extends Controller
{
    /** Packed castings not yet dispatched, whether made for an order or for stock. */
    public function index(ShipmentService $ship): View
    {
        $items = PackingRecordItem::with(['lot.product', 'lot.productionOrder.salesOrderLine.salesOrder.customer', 'packing'])->get()
            ->map(function ($i) use ($ship) { $i->left = $ship->undispatched($i); return $i; })->filter(fn ($i) => $i->left > 0)->values();
        $byProduct = $items->groupBy(fn ($i) => $i->lot->product?->code ?? '—')->map(fn ($g) => [
            'name' => $g->first()->lot->product?->name, 'qty' => $g->sum('left'), 'rows' => $g,
            'stock_qty' => $g->filter(fn ($i) => ! $i->lot->productionOrder?->salesOrderLine)->sum('left'),
        ])->sortKeys();

        return view('dispatch::finished-goods', ['byProduct' => $byProduct]);
    }
}
