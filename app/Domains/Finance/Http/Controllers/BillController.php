<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Services\Advances;
use App\Domains\Finance\Services\BillService;
use App\Domains\Finance\Services\Payables;
use App\Domains\Finance\Services\FinanceRules;
use App\Domains\Finance\Models\TaxRate;
use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\Supplier;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class BillController extends Controller
{
    public function index(Request $request): View
    {
        $supplierId = $request->filled('supplier') && Str::isUuid($request->query('supplier')) ? $request->query('supplier') : null;
        $status = in_array($request->query('status'), ['DRAFT', 'POSTED', 'VOID', 'OPEN', 'OVERDUE'], true) ? $request->query('status') : null;
        $query = SupplierBill::with('supplier')->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when(in_array($status, ['DRAFT', 'POSTED', 'VOID'], true), fn ($q) => $q->where('status', $status))
            ->when(in_array($status, ['OPEN', 'OVERDUE'], true), fn ($q) => $q->where('status', 'POSTED')->where('kind', 'BILL'))
            ->orderByDesc('bill_date')->orderByDesc('created_at');
        $rows = in_array($status, ['OPEN', 'OVERDUE'], true)
            ? $query->get()->filter(fn (SupplierBill $b) => $status === 'OPEN' ? $b->balance() > 0 : $b->isOverdue())->values()
            : $query->paginate(25)->withQueryString();

        return view('finance::bills.index', [
            'summary' => app(Payables::class)->summary(), 'bills' => $rows, 'supplierId' => $supplierId, 'status' => $status,
            'suppliers' => Supplier::whereIn('id', SupplierBill::select('supplier_id'))->orderedByName()->get(),
        ]);
    }

    /** A blank form, or one filled from a purchase order (?order=) or for a supplier (?supplier=). */
    public function create(Request $request, BillService $service): View
    {
        $head = ['supplier_id' => null, 'purchase_order_id' => null, 'payment_terms' => null];
        $lines = [];
        $notice = null;
        if ($request->filled('order') && Str::isUuid($request->query('order'))) {
            $order = PurchaseOrder::with('lines')->findOrFail($request->query('order'));
            $lines = $service->linesFromOrder($order);
            $head = ['supplier_id' => $order->supplier_id, 'purchase_order_id' => $order->id, 'payment_terms' => $order->payment_terms, 'currency' => $order->currency];
            $notice = $lines === [] ? "Everything received on {$order->po_number} is already billed." : "Lines from {$order->po_number}: what was received and is not yet billed.";
        } elseif ($request->filled('supplier') && Str::isUuid($request->query('supplier'))) {
            $head['supplier_id'] = $request->query('supplier');
        }

        return view('finance::bills.form', $this->formData(null, $head, $lines) + ['notice' => $notice]);
    }

    public function store(Request $request, BillService $service): RedirectResponse
    {
        try {
            $bill = $service->saveDraft($this->head($request), $this->lines($request));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['bill' => $e->getMessage()])->withInput();
        }

        return redirect()->route('bills.show', $bill)->with('success', 'Draft saved. Post it when it agrees with the supplier\'s bill.');
    }

    public function show(SupplierBill $bill, BillService $service): View
    {
        $bill->load(['supplier', 'lines', 'creditNotes', 'original', 'allocations.payment', 'purchaseOrder']);

        return view('finance::bills.show', ['bill' => $bill, 'issues' => $bill->status === 'DRAFT' ? $service->matchIssues($bill) : []]);
    }

    /** Puts the supplier advances on this bill. */
    public function applyAdvances(SupplierBill $bill, Advances $advances): RedirectResponse
    {
        $applied = $advances->applyToBill($bill);

        return back()->with('success', $applied > 0 ? 'Advances put on this bill: '.number_format($applied, 2).'.' : 'No advance could go on this bill.');
    }

    public function edit(SupplierBill $bill): View|RedirectResponse
    {
        if ($bill->status !== 'DRAFT') {
            return redirect()->route('bills.show', $bill)->withErrors(['bill' => 'Only a draft can be changed.']);
        }
        $lines = $bill->lines->map(fn ($l) => $l->only(['description', 'quantity', 'uom', 'unit_price', 'tax_percent', 'purchase_order_line_id', 'account_id', 'supply_type'])
            + ['treat' => $l->fixed_asset_id ? 'asset:'.$l->fixed_asset_id : ($l->pattern_development_id ? 'dev:'.$l->pattern_development_id : '')])->all();

        return view('finance::bills.form', $this->formData($bill, $bill->only(['supplier_id', 'purchase_order_id', 'payment_terms', 'currency']), $lines) + ['notice' => null]);
    }

    public function update(Request $request, SupplierBill $bill, BillService $service): RedirectResponse
    {
        try {
            $service->saveDraft($this->head($request), $this->lines($request), $bill);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['bill' => $e->getMessage()])->withInput();
        }

        return redirect()->route('bills.show', $bill)->with('success', 'Draft saved.');
    }

    public function post(SupplierBill $bill, BillService $service): RedirectResponse
    {
        try {
            $bill = $service->post($bill);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['bill' => $e->getMessage()]);
        }

        return back()->with('success', "{$bill->bill_number} posted.");
    }

    public function void(Request $request, SupplierBill $bill, BillService $service): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);
        try {
            $service->void($bill, $d['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['bill' => $e->getMessage()]);
        }

        return back()->with('success', "{$bill->bill_number} voided.");
    }

    public function destroy(SupplierBill $bill): RedirectResponse
    {
        if ($bill->status !== 'DRAFT') {
            return back()->withErrors(['bill' => 'Only a draft can be deleted; void a posted bill instead.']);
        }
        $bill->delete();

        return redirect()->route('bills.index')->with('success', 'Draft deleted.');
    }

    /** The credit note form for a bill, with its lines ready to trim. */
    public function credit(SupplierBill $bill): View|RedirectResponse
    {
        if ($bill->status !== 'POSTED' || $bill->isCreditNote()) {
            return redirect()->route('bills.show', $bill)->withErrors(['bill' => 'A credit note can only be entered against a posted bill.']);
        }
        $bill->load(['lines', 'supplier']);

        return view('finance::bills.credit', ['bill' => $bill]);
    }

    public function storeCredit(Request $request, SupplierBill $bill, BillService $service): RedirectResponse
    {
        $d = $request->validate(['supplier_bill_number' => ['required', 'string', 'max:100'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        try {
            $note = $service->creditNote($bill, $this->lines($request), $d['supplier_bill_number'], $d['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['bill' => $e->getMessage()])->withInput();
        }

        return redirect()->route('bills.show', $note)->with('success', "Credit note {$note->bill_number} posted against {$bill->bill_number}.");
    }

    /** @return array<string, mixed> */
    private function formData(?SupplierBill $bill, array $head, array $lines): array
    {
        $default = (float) (TaxRate::where('is_active', true)->where('is_default', true)->value('rate_percent') ?? 0);

        return [
            'bill' => $bill, 'head' => $head,
            'lines' => $lines ?: [['description' => '', 'quantity' => 1, 'uom' => '', 'unit_price' => 0, 'tax_percent' => $default, 'purchase_order_line_id' => null, 'account_id' => null]],
            'suppliers' => Supplier::orderedByName()->get(), 'ledgerAccounts' => self::ledgerAccounts(), 'treatOptions' => self::treatOptions(), 'currency' => $bill?->currency ?? ($head['currency'] ?? null) ?: FinanceRules::currency(),
        ];
    }

    /**
     * The accounts a bill line can be charged to when the general ledger is on: costs and expenses, then the rest.
     *
     * @return \Illuminate\Support\Collection<int, array{id: string, label: string}>
     */
    private static function ledgerAccounts(): \Illuminate\Support\Collection
    {
        if (! app(\App\Domains\Core\ModuleRegistry::class)->isEnabled('ledger')) {
            return collect();
        }

        return \App\Domains\Ledger\Models\LedgerAccount::postable()->orderByRaw("type <> 'EXPENSE'")->orderBy('code')->get()
            ->map(fn ($a) => ['id' => $a->id, 'label' => $a->label()]);
    }

    /**
     * What a line can be treated as besides an expense or stock (design 28g): an asset in progress, or a pattern development still open.
     * The note says where it posts; a development goes to work in progress when the customer pays for the tool.
     *
     * @return list<array{value: string, label: string, note: string}>
     */
    private static function treatOptions(): array
    {
        $registry = app(\App\Domains\Core\ModuleRegistry::class);
        $options = [];
        if ($registry->isEnabled('assets')) {
            foreach (\App\Domains\Assets\Models\FixedAsset::where('status', 'IN_PROGRESS')->orderBy('code')->get(['id', 'code', 'name']) as $a) {
                $options[] = ['value' => 'asset:'.$a->id, 'label' => "Asset · {$a->code} {$a->name}", 'note' => '21-04-01 capital work in progress'];
            }
        }
        if ($registry->isEnabled('development')) {
            foreach (\App\Domains\Development\Models\PatternDevelopment::where('status', 'OPEN')->orderBy('code')->get(['id', 'code', 'who_pays']) as $d) {
                $customer = in_array($d->who_pays, ['CUSTOMER', 'SHARED'], true);
                $options[] = ['value' => 'dev:'.$d->id, 'label' => "Development · {$d->code}", 'note' => $customer ? '22-08-04 WIP, customer pays' : '21-04-01 capital work in progress'];
            }
        }

        return $options;
    }

    /** @return array<string, mixed> */
    private function head(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'], 'supplier_bill_number' => ['nullable', 'string', 'max:100'], 'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'], 'currency' => ['nullable', 'string', 'max:10'], 'payment_terms' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'], 'purchase_order_id' => ['nullable', 'uuid', 'exists:purchase_orders,id'],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function lines(Request $request): array
    {
        return array_values($request->validate([
            'lines' => ['required', 'array', 'min:1'], 'lines.*.description' => ['nullable', 'string', 'max:300'], 'lines.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.uom' => ['nullable', 'string', 'max:20'], 'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'], 'lines.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.purchase_order_line_id' => ['nullable', 'uuid'], 'lines.*.account_id' => ['nullable', 'uuid'], 'lines.*.supply_type' => ['nullable', 'in:GOODS,SERVICE'],
            'lines.*.treat' => ['nullable', 'regex:/^(asset|dev):[0-9a-f-]{36}$/'],
        ])['lines']);
    }
}
