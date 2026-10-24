<?php

namespace App\Domains\Purchasing\Http\Controllers;

use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\Supplier;
use App\Domains\Purchasing\Services\PurchaseOrderService;
use App\Domains\Purchasing\Support\BuyingRules;
use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Services\Approvals\ApprovalGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $supplierId = $request->filled('supplier') && Str::isUuid($request->query('supplier')) ? $request->query('supplier') : null;
        $status = array_key_exists((string) $request->query('status'), PurchaseOrder::STATUSES) || $request->query('status') === 'OPEN' ? $request->query('status') : null;
        $orders = PurchaseOrder::with('supplier')->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($status === 'OPEN', fn ($q) => $q->whereIn('status', PurchaseOrder::RECEIVING))
            ->when($status && $status !== 'OPEN', fn ($q) => $q->where('status', $status))
            ->orderByDesc('order_date')->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('purchasing::orders.index', [
            'orders' => $orders, 'supplierId' => $supplierId, 'status' => $status,
            'suppliers' => Supplier::whereIn('id', PurchaseOrder::select('supplier_id'))->orderedByName()->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $head = ['supplier_id' => $request->filled('supplier') && Str::isUuid($request->query('supplier')) ? $request->query('supplier') : null];

        return view('purchasing::orders.form', $this->formData(null, $head, []));
    }

    public function store(Request $request, PurchaseOrderService $service): RedirectResponse
    {
        try {
            $order = $service->saveDraft($this->head($request), $this->lines($request));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['order' => $e->getMessage()])->withInput();
        }

        return redirect()->route('purchase-orders.show', $order)->with('success', 'Draft saved. Issue it when it is right.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'lines.material', 'lines.lots']);

        return view('purchasing::orders.show', ['order' => $purchaseOrder]);
    }

    public function print(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'lines']);

        return view('purchasing::orders.print', ['order' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if ($purchaseOrder->status !== 'DRAFT') {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->withErrors(['order' => 'Only a draft can be changed.']);
        }
        $lines = $purchaseOrder->lines->map(fn ($l) => $l->only(['material_id', 'description', 'ordered_qty', 'uom', 'unit_price', 'tax_percent']))->all();

        return view('purchasing::orders.form', $this->formData($purchaseOrder, $purchaseOrder->only(['supplier_id']), $lines));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        try {
            $service->saveDraft($this->head($request), $this->lines($request), $purchaseOrder);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['order' => $e->getMessage()])->withInput();
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Draft saved.');
    }

    public function issue(PurchaseOrder $purchaseOrder, PurchaseOrderService $service, ApprovalGate $gate): RedirectResponse
    {
        $message = null;
        $label = 'Purchase order to '.$purchaseOrder->supplier?->name.' for '.number_format($purchaseOrder->total, 2);
        if (! $gate->passes('purchase_order.issue', 'PurchaseOrder', (string) $purchaseOrder->id, $label, $message)) {
            return back()->with('info', $message);
        }
        try {
            $order = $service->issue($purchaseOrder);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return back()->with('success', "{$order->po_number} issued.");
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $d = $request->validate([
            'qty' => ['required', 'array'], 'qty.*' => ['nullable', 'numeric', 'min:0'], 'supplier_lot_number' => ['nullable', 'string', 'max:100'], 'received_on' => ['nullable', 'date'],
        ]);
        try {
            $lots = $service->receive($purchaseOrder, $d['qty'], $d['supplier_lot_number'] ?? null, $d['received_on'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['order' => $e->getMessage()])->withInput();
        }

        return back()->with('success', $lots === [] ? 'Received.' : 'Received. Lot '.collect($lots)->pluck('lot_number')->implode(', ').' waits in quarantine for the incoming checks.');
    }

    public function close(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);
        try {
            $service->close($purchaseOrder, $d['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return back()->with('success', 'Order closed.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== 'DRAFT') {
            return back()->withErrors(['order' => 'Only a draft can be deleted; cancel or close an issued order instead.']);
        }
        $purchaseOrder->delete();

        return redirect()->route('purchase-orders.index')->with('success', 'Draft deleted.');
    }

    public function editSettings(): View
    {
        return view('settings.rules', [
            'title' => 'Purchase order rules', 'intro' => 'How receiving against purchase orders behaves.',
            'rules' => BuyingRules::RULES, 'values' => collect(BuyingRules::RULES)->map(fn ($r, $k) => BuyingRules::get($k))->all(),
            'action' => route('purchase-orders.settings.update'), 'backUrl' => route('purchase-orders.index'), 'backLabel' => 'Purchase orders',
            'note' => 'To have orders approved before they go to the supplier, add a policy for "Issue a purchase order" under Approvals.',
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (BuyingRules::RULES as $key => [, , $options]) {
            $rules[$key] = ['required', Rule::in(array_keys($options))];
        }
        foreach ($request->validate($rules) as $key => $value) {
            BuyingRules::set($key, $value);
        }

        return back()->with('success', 'Purchase order rules saved.');
    }

    /** @return array<string, mixed> */
    private function formData(?PurchaseOrder $order, array $head, array $lines): array
    {
        return [
            'order' => $order, 'head' => $head,
            'lines' => $lines ?: [['material_id' => '', 'description' => '', 'ordered_qty' => 1, 'uom' => '', 'unit_price' => 0, 'tax_percent' => 0]],
            'suppliers' => Supplier::whereNotIn('approval_status', Supplier::BLOCKED)->orderedByName()->get(),
            'materials' => Material::where('status', 'ACTIVE')->orderBy('code')->get(['id', 'code', 'name', 'base_uom', 'standard_cost_per_uom']),
        ];
    }

    /** @return array<string, mixed> */
    private function head(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'uuid', Rule::exists('suppliers', 'id')->whereNull('deleted_at')], 'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'], 'currency' => ['nullable', 'string', 'max:10'], 'payment_terms' => ['nullable', 'string', 'max:100'],
            'supplier_reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function lines(Request $request): array
    {
        return array_values($request->validate([
            'lines' => ['required', 'array', 'min:1'], 'lines.*.material_id' => ['nullable', 'uuid', 'exists:materials,id'], 'lines.*.description' => ['nullable', 'string', 'max:300'],
            'lines.*.ordered_qty' => ['nullable', 'numeric', 'gt:0'], 'lines.*.uom' => ['nullable', 'string', 'max:20'], 'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ])['lines']);
    }
}
