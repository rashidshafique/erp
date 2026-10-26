<?php

namespace App\Domains\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Domains\Sales\Services\SalesOrderStatus;
use App\Support\OrderRules;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Domains\Sales\Models\SalesOrderLineDelivery;
use App\Domains\Sales\Services\ContractReviewer;
use App\Domains\Sales\Services\SalesOrderEditor;
use App\Services\Orders\ProductionOrderFactory;
use App\Rules\ProductRevisionChoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesOrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), SalesOrder::STATUSES) || $request->query('status') === 'OPEN' ? $request->query('status') : null;
        $search = trim((string) $request->query('search'));
        $base = SalesOrder::query()->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('order_number', 'ilike', "%{$search}%")
            ->orWhere('customer_reference', 'ilike', "%{$search}%")->orWhereHas('customer', fn ($c) => $c->matching($search))));
        $orders = (clone $base)->with('customer')->withCount('lines')
            ->when($status === 'OPEN', fn ($q) => $q->whereIn('status', SalesOrder::OPEN))
            ->when($status && $status !== 'OPEN', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')->paginate(20)->withQueryString();
        $counts = (clone $base)->groupBy('status')->selectRaw('status, count(*) as n')->pluck('n', 'status')->map(fn ($n) => (int) $n);

        return view('sales::orders.index', [
            'orders' => $orders, 'status' => $status, 'search' => $search,
            'counts' => $counts->all() + ['OPEN' => $counts->only(SalesOrder::OPEN)->sum(), '' => $counts->sum()],
        ]);
    }

    public function create(): View
    {
        return view('sales::orders.create', [
            'customers' => Customer::active()->orderBy('code')->get(),
            'revisions' => ProductRevision::with('product')->whereIn('status', ['APPROVED', 'ACTIVE'])->get()
                ->sortBy(fn ($r) => $r->product?->code.' '.$r->revision)->values(),
            'bareProducts' => Product::withoutRevision()->orderBy('code')->get(),
            // The last price each product was sold at, offered as the starting price of a new line.
            'lastPrices' => DB::table('sales_order_lines')->whereNotNull('unit_price')->selectRaw('DISTINCT ON (product_id) product_id, unit_price')
                ->orderBy('product_id')->orderByDesc('created_at')->pluck('unit_price', 'product_id'),
        ]);
    }

    public function store(Request $request, SalesOrderStatus $status): RedirectResponse
    {
        $request->merge(['lines' => array_values(array_filter((array) $request->input('lines', []), fn ($l) => ! empty($l['product_revision_id'])))]);

        $data = $request->validate([
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'customer_reference' => ['nullable', 'string', 'max:100'],
            'order_date' => ['required', 'date'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_revision_id' => ['required', new ProductRevisionChoice],
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
                $rev = ProductRevision::fromChoice($l['product_revision_id']);
                $order->lines()->create([
                    'product_id' => $rev->product_id, 'product_revision_id' => $rev->id, 'line_number' => $i + 1,
                    'ordered_qty' => $l['ordered_qty'], 'unit_price' => $l['unit_price'] ?? null, 'uom' => 'piece',
                    'requested_delivery_date' => $l['requested_delivery_date'] ?? null,
                ]);
            }

            return $order;
        });

        if (OrderRules::sendsStraightToProduction() && ! OrderRules::reviewRequired()) {
            $status->change($order, 'IN_PRODUCTION', true);

            return redirect()->route('sales-orders.show', $order)->with('success', "Order {$order->order_number} saved and sent to production.");
        }

        return redirect()->route('sales-orders.show', $order)->with('success', "Order {$order->order_number} saved. Run the contract review next.");
    }

    public function show(SalesOrder $salesOrder): View
    {
        $salesOrder->load(['customer', 'lines.product', 'lines.productRevision', 'lines.productionOrders', 'lines.deliveries']);
        $review = $salesOrder->currentReview();
        $review?->load(['items.line.product']);

        $shipped = \Illuminate\Support\Facades\DB::table('dispatch_items')->whereIn('sales_order_line_id', $salesOrder->lines->pluck('id'))->selectRaw('sales_order_line_id, sum(quantity_dispatched) q')->groupBy('sales_order_line_id')->pluck('q', 'sales_order_line_id');

        return view('sales::orders.show', ['order' => $salesOrder, 'review' => $review, 'shipped' => $shipped]);
    }

    public function edit(SalesOrder $salesOrder, SalesOrderEditor $editor): View|RedirectResponse
    {
        if (! $editor->isEditable($salesOrder)) {
            return redirect()->route('sales-orders.show', $salesOrder)->withErrors(['order' => 'A closed order cannot be edited.']);
        }
        $salesOrder->load(['lines.product', 'lines.productionOrders']);

        return view('sales::orders.edit', ['order' => $salesOrder, 'needsReason' => $editor->needsReason($salesOrder)] + $this->create()->getData());
    }

    public function update(Request $request, SalesOrder $salesOrder, SalesOrderEditor $editor): RedirectResponse
    {
        $request->merge(['lines' => array_values(array_filter((array) $request->input('lines', []), fn ($l) => empty($l['remove']) && (! empty($l['id']) || ! empty($l['product_revision_id']))))]);

        $data = $request->validate([
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'customer_reference' => ['nullable', 'string', 'max:100'],
            'order_date' => ['required', 'date'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'reason' => [$editor->needsReason($salesOrder) ? 'required' : 'nullable', 'string', 'min:5', 'max:300'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.id' => ['nullable', 'uuid'],
            'lines.*.product_revision_id' => ['required_without:lines.*.id', 'nullable', new ProductRevisionChoice],
            'lines.*.ordered_qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.requested_delivery_date' => ['nullable', 'date'],
        ]);
        $reviewStale = $editor->update($salesOrder, $data, $data['reason'] ?? null);

        return redirect()->route('sales-orders.show', $salesOrder)->with('success', "Order {$salesOrder->order_number} updated.".($reviewStale ? ' The lines changed after the contract review was approved, so run the review again.' : ''));
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

    public function changeStatus(Request $request, SalesOrder $salesOrder, SalesOrderStatus $status): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys($status->allowedFrom($salesOrder)))],
            'release_all' => ['nullable', 'boolean'],
            'reason' => ['required_if:status,CANCELLED', 'nullable', 'string', 'max:300'],
        ]);
        $status->change($salesOrder, $data['status'], (bool) ($data['release_all'] ?? OrderRules::sendsStraightToProduction()), $data['reason'] ?? null);

        return back()->with('success', 'Order status updated.');
    }

    public function addDelivery(Request $request, SalesOrder $salesOrder, SalesOrderLine $line): RedirectResponse
    {
        abort_unless($line->sales_order_id === $salesOrder->id, 404);
        $data = $request->validate([
            'due_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);
        if (! in_array($salesOrder->status, SalesOrder::OPEN, true)) {
            return back()->withErrors(['quantity' => 'The delivery schedule of a closed order cannot change.']);
        }
        $room = (float) $line->ordered_qty - (float) $line->deliveries()->sum('quantity');
        if ((float) $data['quantity'] > $room + 0.0005) {
            return back()->withErrors(['quantity' => 'Line '.$line->line_number.' has only '.rtrim(rtrim(number_format(max($room, 0), 3, '.', ''), '0'), '.').' left to schedule.'])->withInput();
        }
        $line->deliveries()->create($data);

        return back()->with('success', 'Delivery added to line '.$line->line_number.'.');
    }

    public function removeDelivery(SalesOrder $salesOrder, SalesOrderLine $line, SalesOrderLineDelivery $delivery): RedirectResponse
    {
        abort_unless($line->sales_order_id === $salesOrder->id && $delivery->sales_order_line_id === $line->id, 404);
        abort_unless(in_array($salesOrder->status, SalesOrder::OPEN, true), 403);
        $delivery->delete();

        return back()->with('success', 'Delivery removed.');
    }
}
