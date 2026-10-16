<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ProductRevision;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Orders\ContractReviewer;
use App\Services\Orders\ProductionOrderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = SalesOrder::with('customer')->withCount('lines')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->where('order_number', 'ilike', '%'.trim($request->search).'%'))
            ->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('sales-orders.index', compact('orders'));
    }

    public function create(): View
    {
        return view('sales-orders.create', [
            'customers' => Customer::where('active', true)->orderBy('code')->get(),
            'revisions' => ProductRevision::with('product')->whereIn('status', ['APPROVED', 'ACTIVE'])->get()
                ->sortBy(fn ($r) => $r->product?->code.' '.$r->revision)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['lines' => array_values(array_filter((array) $request->input('lines', []), fn ($l) => ! empty($l['product_revision_id'])))]);

        $data = $request->validate([
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'customer_reference' => ['nullable', 'string', 'max:100'],
            'order_date' => ['required', 'date'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_revision_id' => ['required', 'uuid', 'exists:product_revisions,id'],
            'lines.*.ordered_qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.requested_delivery_date' => ['nullable', 'date'],
        ]);

        $order = DB::transaction(function () use ($data) {
            $order = SalesOrder::create([
                'order_number' => SalesOrder::nextNumber(),
                'customer_id' => $data['customer_id'], 'order_date' => $data['order_date'],
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'customer_reference' => $data['customer_reference'] ?? null, 'notes' => $data['notes'] ?? null, 'status' => 'DRAFT',
            ]);
            foreach (array_values($data['lines']) as $i => $l) {
                $rev = ProductRevision::findOrFail($l['product_revision_id']);
                $order->lines()->create([
                    'product_id' => $rev->product_id, 'product_revision_id' => $rev->id, 'line_number' => $i + 1,
                    'ordered_qty' => $l['ordered_qty'], 'unit_price' => $l['unit_price'] ?? null, 'uom' => 'piece',
                    'requested_delivery_date' => $l['requested_delivery_date'] ?? null,
                ]);
            }

            return $order;
        });

        return redirect()->route('sales-orders.show', $order)->with('success', "Order {$order->order_number} saved. Run the contract review next.");
    }

    public function show(SalesOrder $salesOrder): View
    {
        $salesOrder->load(['customer', 'lines.product', 'lines.productRevision', 'lines.productionOrders']);
        $review = $salesOrder->currentReview();
        $review?->load(['items.line.product']);

        $shipped = \Illuminate\Support\Facades\DB::table('dispatch_items')->whereIn('sales_order_line_id', $salesOrder->lines->pluck('id'))->selectRaw('sales_order_line_id, sum(quantity_dispatched) q')->groupBy('sales_order_line_id')->pluck('q', 'sales_order_line_id');

        return view('sales-orders.show', ['order' => $salesOrder, 'review' => $review, 'shipped' => $shipped]);
    }

    public function review(SalesOrder $salesOrder, ContractReviewer $reviewer): RedirectResponse
    {
        if (in_array($salesOrder->status, ['CANCELLED', 'DISPATCHED'], true)) {
            return back()->withErrors(['review' => 'This order is closed.']);
        }
        $r = $reviewer->evaluate($salesOrder);

        return redirect()->route('sales-orders.show', $salesOrder)->with('success', "Review {$r->review_number} evaluated: {$r->verdict}.");
    }

    public function acknowledge(Request $request, SalesOrder $salesOrder, ContractReviewer $reviewer): RedirectResponse
    {
        $data = $request->validate(['item_id' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:500']]);
        $review = $salesOrder->currentReview();
        abort_unless($review, 404);
        $reviewer->acknowledge($review, $data['item_id'], $data['reason']);

        return back()->with('success', 'Warning acknowledged.');
    }

    public function sign(Request $request, SalesOrder $salesOrder, ContractReviewer $reviewer): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'in:sales,quality'], 'name' => ['required', 'string', 'max:100']]);
        $review = $salesOrder->currentReview();
        abort_unless($review, 404);
        $r = $reviewer->sign($review, $data['role'], $data['name']);

        return back()->with('success', $r->status === 'APPROVED' ? 'Review approved. The order is confirmed.' : ucfirst($data['role']).' sign-off recorded.');
    }

    public function productionOrder(Request $request, SalesOrder $salesOrder, SalesOrderLine $line, ProductionOrderFactory $factory): RedirectResponse
    {
        abort_unless($line->sales_order_id === $salesOrder->id, 404);
        $data = $request->validate(['qty' => ['nullable', 'numeric', 'gt:0']]);
        $po = $factory->forSalesLine($line, isset($data['qty']) ? (float) $data['qty'] : null);

        return redirect()->route('sales-orders.show', $salesOrder)->with('success', "Production order {$po->production_order_number} created as a draft.");
    }
}
