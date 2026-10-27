<?php

namespace App\Domains\Dispatch\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Dispatch\Models\PackingRecordItem;
use App\Domains\Dispatch\Services\ShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class DispatchController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->filled('customer') && Str::isUuid($request->query('customer')) ? $request->query('customer') : null;
        $dispatches = Dispatch::with('customer')->withCount('items')
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('dispatch::dispatches.index', [
            'dispatches' => $dispatches, 'customerId' => $customerId,
            'customers' => Customer::whereIn('id', Dispatch::select('customer_id'))->orderedByName()->get(),
        ]);
    }

    public function create(ShipmentService $ship): View
    {
        $items = PackingRecordItem::with(['lot.product', 'lot.productionOrder.salesOrderLine.salesOrder.customer', 'packing'])->get()
            ->map(function ($i) use ($ship) { $i->left = $ship->undispatched($i); return $i; })->filter(fn ($i) => $i->left > 0)->values();

        return view('dispatch::dispatches.create', ['items' => $items, 'customers' => Customer::orderedByName()->get()]);
    }

    public function store(Request $request, ShipmentService $ship): RedirectResponse
    {
        $d = $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:customers,id'], 'qty' => ['required', 'array'], 'qty.*' => ['nullable', 'integer', 'min:0'],
            'delivery_challan_number' => ['nullable', 'string', 'max:60'], 'carrier_name' => ['nullable', 'string', 'max:100'],
            'vehicle_number' => ['nullable', 'string', 'max:40'], 'driver_name' => ['nullable', 'string', 'max:100'],
            'delivery_address' => ['nullable', 'string', 'max:500'], 'dispatched_by' => ['nullable', 'string', 'max:100'],
        ]);
        $credit = app(\App\Domains\Core\Contracts\CreditControl::class)->check($d['customer_id']);
        if ($credit['severity'] === 'BLOCK') {
            return back()->withErrors(['dispatch' => 'Credit: '.implode(' ', $credit['messages'])])->withInput();
        }
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $customer = \App\Models\Customer::find($d['customer_id']);
            if (! app(\App\Services\Approvals\ApprovalGate::class)->passes('dispatch.create', 'Customer', (string) $d['customer_id'], 'Dispatch to '.($customer->name ?? $d['customer_id']), $msg)) {
                \Illuminate\Support\Facades\DB::commit();   // keep the request that was just raised

                return back()->withErrors(['dispatch' => $msg])->withInput();
            }
            $disp = $ship->dispatch($d['qty'], $d);
            \Illuminate\Support\Facades\DB::commit();
        } catch (InvalidArgumentException $e) {
            \Illuminate\Support\Facades\DB::rollBack();   // an approval consumed by a failed dispatch is given back

            return back()->withErrors(['dispatch' => $e->getMessage()])->withInput();
        }

        $done = redirect()->route('dispatches.show', $disp)->with('success', "Dispatch {$disp->dispatch_number} recorded.");

        return $credit['severity'] === 'WARN' ? $done->with('warning', 'Credit: '.implode(' ', $credit['messages'])) : $done;
    }

    public function show(Dispatch $dispatch): View
    {
        $dispatch->load(['customer', 'items.packingItem.lot.product', 'items.salesOrderLine.salesOrder']);

        return view('dispatch::dispatches.show', ['dispatch' => $dispatch]);
    }
}
