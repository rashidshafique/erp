<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\ProductionOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = ProductionOrder::query()
            ->with(['product', 'productRevision'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . $request->string('search')->toString() . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('production_order_number', 'ilike', $term)
                        ->orWhereHas('product', function ($productQuery) use ($term) {
                            $productQuery
                                ->where('code', 'ilike', $term)
                                ->orWhere('name', 'ilike', $term);
                        });
                });
            })
            ->orderByRaw("
                CASE priority
                    WHEN 'URGENT' THEN 1
                    WHEN 'HIGH' THEN 2
                    WHEN 'NORMAL' THEN 3
                    WHEN 'LOW' THEN 4
                    ELSE 5
                END
            ")
            ->orderBy('required_date')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'draft' => ProductionOrder::where('status', 'DRAFT')->count(),
            'released' => ProductionOrder::where('status', 'RELEASED')->count(),
            'in_progress' => ProductionOrder::where('status', 'IN_PROGRESS')->count(),
            'completed' => ProductionOrder::where('status', 'COMPLETED')->count(),
        ];

        return view('production-orders.index', compact(
            'orders',
            'counts'
        ));
    }

    public function create(): View
    {
        $products = Product::query()
            ->where('active', true)
            ->with(['revisions' => function ($query) {
                $query->orderByDesc('effective_from')
                    ->orderByDesc('revision');
            }])
            ->orderBy('code')
            ->get();

        return view('production-orders.create', compact('products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_revision_id' => [
                'required',
                'uuid',
                'exists:product_revisions,id',
            ],
            'order_type' => [
                'required',
                Rule::in(['CUSTOMER', 'STOCK', 'REWORK']),
            ],
            'priority' => [
                'required',
                Rule::in(['LOW', 'NORMAL', 'HIGH', 'URGENT']),
            ],
            'planned_qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'uom' => ['required', 'string', 'max:20'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_finish_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'required_date' => ['nullable', 'date'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $revision = ProductRevision::query()
            ->with('product')
            ->findOrFail($validated['product_revision_id']);

        if (
            !$revision->product ||
            !$revision->product->active ||
            !in_array($revision->status, ['APPROVED', 'ACTIVE'], true)
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'product_revision_id' =>
                        'Choose an active revision belonging to an active product.',
                ]);
        }

        $order = DB::transaction(function () use ($validated, $revision) {
            // Serialize number generation so simultaneous saves cannot share a number.
            DB::select('SELECT pg_advisory_xact_lock(?)', [2026101101]);

            $prefix = 'PO-' . now()->format('Y') . '-';

            $lastSequence = ProductionOrder::query()
                ->where('production_order_number', 'like', $prefix . '%')
                ->pluck('production_order_number')
                ->map(fn ($existing) => (int) substr($existing, strlen($prefix)))
                ->max() ?? 0;

            $number = $prefix . str_pad(
                (string) ($lastSequence + 1),
                3,
                '0',
                STR_PAD_LEFT
            );

            return ProductionOrder::create([
                'production_order_number' => $number,
            'product_id' => $revision->product_id,
            'product_revision_id' => $revision->id,
            'order_type' => $validated['order_type'],
            'status' => 'DRAFT',
            'priority' => $validated['priority'],
            'planned_qty' => $validated['planned_qty'],
            'released_qty' => 0,
            'completed_qty' => 0,
            'rejected_qty' => 0,
            'uom' => $validated['uom'],
            'planned_start_date' => $validated['planned_start_date'] ?? null,
            'planned_finish_date' => $validated['planned_finish_date'] ?? null,
            'required_date' => $validated['required_date'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('production-orders.index')
            ->with('success', "Production order {$order->production_order_number} created as a draft.");
    }

    public function release(ProductionOrder $productionOrder): RedirectResponse
    {
        if ($productionOrder->status === 'DRAFT' && ! app(\App\Services\Approvals\ApprovalGate::class)
            ->passes('production_order.release', 'ProductionOrder', (string) $productionOrder->id, $productionOrder->production_order_number, $msg)) {
            return back()->withErrors(['release' => $msg]);
        }
        $blocked = null;
        $released = DB::transaction(function () use ($productionOrder, &$blocked) {
            $order = ProductionOrder::query()
                ->lockForUpdate()
                ->findOrFail($productionOrder->id);

            if ($order->status !== 'DRAFT') {
                return false;
            }

            if ((float) $order->planned_qty <= 0) {
                return false;
            }

            if (config('foundry.require_contract_review') && $order->sales_order_line_id) {
                $so = \App\Models\SalesOrderLine::with('salesOrder')->find($order->sales_order_line_id)?->salesOrder;
                if ($so && ! $so->hasApprovedReview()) {
                    $blocked = "Order {$so->order_number} has no approved contract review.";

                    return false;
                }
            }

            $order->status = 'RELEASED';
            $order->released_qty = $order->planned_qty;
            $order->save();

            return true;
        });

        if (!$released) {
            return back()->withErrors([
                'release' => $blocked ?? 'Only draft orders with a positive planned quantity can be released.',
            ]);
        }

        return redirect()
            ->route('production-orders.index')
            ->with('success', "Production order {$productionOrder->production_order_number} released.");
    }
}
