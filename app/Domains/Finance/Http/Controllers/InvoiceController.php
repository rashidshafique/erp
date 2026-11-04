<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\TaxRate;
use App\Domains\Core\ModuleRegistry;
use App\Domains\Finance\Services\Advances;
use App\Domains\Finance\Services\CreditLimits;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\PostingRules;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Domains\Finance\Services\FinanceRules;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Finance\Services\InvoiceTemplates;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    /** The list is a Livewire component (design 24a); `?status=` and `?customer=` in the address still set its tab and customer. */
    public function index(): View
    {
        return view('finance::invoices.index');
    }

    /** A blank form, or one filled from a dispatch (?dispatch=), a sales order (?order=) or a customer (?customer=). */
    public function create(Request $request, InvoiceService $service): View|RedirectResponse
    {
        $head = ['customer_id' => null, 'source_type' => 'manual', 'source_id' => null, 'customer_reference' => null];
        $lines = [];
        $notice = null;
        if ($request->filled('dispatch') && Str::isUuid($request->query('dispatch')) && class_exists(Dispatch::class)) {
            $dispatch = Dispatch::with(['items.salesOrderLine.product', 'items.packingItem.lot.product'])->findOrFail($request->query('dispatch'));
            $lines = $service->linesFromDispatch($dispatch);
            $head = ['customer_id' => $dispatch->customer_id, 'source_type' => 'dispatch', 'source_id' => $dispatch->id, 'customer_reference' => $dispatch->delivery_challan_number ? 'Challan '.$dispatch->delivery_challan_number : null];
            $notice = $lines === [] ? "Everything on dispatch {$dispatch->dispatch_number} is already invoiced." : "Lines from dispatch {$dispatch->dispatch_number}.";
            $lines = array_map(fn ($l) => $l + ['dn' => $dispatch->id], $lines);
        } elseif ($request->filled('order') && Str::isUuid($request->query('order'))) {
            $order = SalesOrder::with(['lines.product'])->findOrFail($request->query('order'));
            $lines = $service->linesFromOrder($order);
            $head = ['customer_id' => $order->customer_id, 'source_type' => 'sales_order', 'source_id' => $order->id, 'customer_reference' => $order->customer_reference ?: $order->order_number];
            $notice = $lines === [] ? "Every line of order {$order->order_number} is already invoiced." : "Lines from order {$order->order_number}: what is not yet invoiced.";
        } elseif ($request->filled('customer') && Str::isUuid($request->query('customer'))) {
            $head['customer_id'] = $request->query('customer');
        }

        return view('finance::invoices.form', $this->formData(null, $head, $lines) + ['notice' => $notice]);
    }

    public function store(Request $request, InvoiceService $service): RedirectResponse
    {
        try {
            $invoice = $service->saveDraft($this->head($request), $this->lines($request));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()])->withInput();
        }

        return redirect()->route('invoices.show', $invoice)->with('success', 'Draft saved. Issue it when it is right.');
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['customer', 'lines', 'creditNotes', 'original', 'allocations.receipt']);
        $draft = $invoice->status === 'DRAFT' && ! $invoice->isCreditNote();
        $checks = $draft ? app(InvoiceService::class)->taxChecks($invoice) : ['blocks' => [], 'warnings' => []];
        $ledger = class_exists(PostingRules::class) && app(ModuleRegistry::class)->isEnabled('ledger');

        return view('finance::invoices.show', [
            'invoice' => $invoice, 'taxChecks' => $checks, 'checkRows' => $draft ? $this->checkRows($invoice, $checks) : [],
            'posting' => $draft && $ledger ? $this->postingRows($invoice) : [],
            'journal' => $ledger && $invoice->status === 'ISSUED' ? Journal::where('source_type', 'invoice')->where('source_id', $invoice->id)->first() : null,
            'trace' => $this->traceByLine($invoice), 'linked' => $this->linkedDocuments($invoice),
            'certificates' => $invoice->certificateAllocations()->with('certificate')->get()->filter(fn ($a) => $a->certificate?->status === 'ACTIVE')->values(),
        ]);
    }

    /**
     * The checks beside a draft: tax, the price against the sales order, and the customer's credit.
     *
     * @param  array{blocks: list<string>, warnings: list<string>}  $tax
     * @return list<array{ok: bool, t: string, d: string}>
     */
    private function checkRows(Invoice $invoice, array $tax): array
    {
        $rows = [];
        foreach ($tax['blocks'] as $text) {
            $rows[] = ['ok' => false, 't' => 'Tax', 'd' => $text];
        }
        foreach ($tax['warnings'] as $text) {
            $rows[] = ['ok' => false, 't' => 'Tax', 'd' => $text];
        }
        if ($tax['blocks'] === [] && $tax['warnings'] === []) {
            $rows[] = ['ok' => true, 't' => 'Tax checks pass', 'd' => 'Rates, supply types and registrations are in order'];
        }

        $orderLines = SalesOrderLine::with('salesOrder')->whereIn('id', $invoice->lines->pluck('sales_order_line_id')->filter())->get()->keyBy('id');
        if ($orderLines->isNotEmpty()) {
            $off = $invoice->lines->filter(fn ($l) => $orderLines->has($l->sales_order_line_id) && abs((float) $orderLines[$l->sales_order_line_id]->unit_price - (float) $l->unit_price) > 0.0001);
            $orders = $orderLines->map(fn ($l) => $l->salesOrder?->order_number)->filter()->unique()->implode(', ');
            $rows[] = $off->isEmpty()
                ? ['ok' => true, 't' => 'Prices match '.$orders, 'd' => 'No line price differs from the order']
                : ['ok' => false, 't' => 'Price differs from '.$orders, 'd' => $off->count().' '.Str::plural('line', $off->count()).' priced otherwise than on the order'];
        }

        if ($invoice->customer_id) {
            $credit = app(CreditLimits::class)->check($invoice->customer_id, (float) $invoice->total);
            $rows[] = $credit['severity'] === 'PASS'
                ? ['ok' => true, 't' => 'Credit is in order', 'd' => 'Posting keeps '.$invoice->customer?->name.' inside its limit']
                : ['ok' => false, 't' => 'Credit', 'd' => implode(' ', $credit['messages'])];
        }

        return $rows;
    }

    /**
     * The journal this draft will post, with each account's code and name (design 24b).
     *
     * @return list<array{code: string, name: string, debit: float, credit: float}>
     */
    private function postingRows(Invoice $invoice): array
    {
        try {
            $lines = app(PostingRules::class)->preview($invoice);
        } catch (\Throwable) {
            return [];
        }
        $accounts = LedgerAccount::whereIn('id', array_column($lines, 'account_id'))->get()->keyBy('id');

        return array_map(fn ($l) => ['code' => (string) $accounts[$l['account_id']]->code, 'name' => (string) $accounts[$l['account_id']]->name, 'debit' => $l['debit'], 'credit' => $l['credit']], $lines);
    }

    /** The dispatch note each line was billed from, keyed by line id. */
    private function traceByLine(Invoice $invoice): array
    {
        return DB::table('invoice_lines')->join('dispatch_items', 'dispatch_items.id', '=', 'invoice_lines.dispatch_item_id')
            ->join('dispatches', 'dispatches.id', '=', 'dispatch_items.dispatch_id')->where('invoice_lines.invoice_id', $invoice->id)
            ->pluck('dispatches.dispatch_number', 'invoice_lines.id')->all();
    }

    /**
     * The sales orders and dispatch notes the invoice was raised from.
     *
     * @return array{orders: list<string>, notes: list<string>}
     */
    private function linkedDocuments(Invoice $invoice): array
    {
        return [
            'orders' => DB::table('invoice_lines')->join('sales_order_lines', 'sales_order_lines.id', '=', 'invoice_lines.sales_order_line_id')
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')->where('invoice_lines.invoice_id', $invoice->id)->distinct()->pluck('sales_orders.order_number')->all(),
            'notes' => array_values(array_unique($this->traceByLine($invoice))),
        ];
    }

    /** Puts the customer's money on account on this invoice. */
    public function applyCredit(Invoice $invoice, Advances $advances): RedirectResponse
    {
        $applied = $advances->applyToInvoice($invoice);

        return back()->with('success', $applied > 0 ? 'Money on account put on this invoice: '.number_format($applied, 2).'.' : 'No money on account could go on this invoice.');
    }

    public function print(Request $request, Invoice $invoice, InvoiceTemplates $templates): View
    {
        $invoice->load(['customer', 'lines', 'original']);
        $key = $templates->has((string) $request->query('template')) ? (string) $request->query('template') : $templates->keyFor($invoice);
        $draw = $templates->render($key);

        return view('finance::invoices.print', ['invoice' => $invoice, 'templateView' => $draw['view'], 'layout' => $draw['layout'], 'registrations' => app(\App\Domains\Core\Contracts\TaxCalculator::class)->registrations($invoice->customer?->party_id)]);
    }

    public function edit(Invoice $invoice): View|RedirectResponse
    {
        if ($invoice->status !== 'DRAFT') {
            return redirect()->route('invoices.show', $invoice)->withErrors(['invoice' => 'Only a draft can be changed.']);
        }
        $lines = $invoice->lines->map(fn ($l) => $l->only(['product_id', 'description', 'quantity', 'uom', 'unit_price', 'discount_percent', 'discount_amount', 'tax_rate_id', 'sales_order_line_id', 'dispatch_item_id', 'supply_type', 'tariff_code', 'inventory_class']))->all();
        $byItem = DB::table('dispatch_items')->whereIn('id', array_filter(array_column($lines, 'dispatch_item_id')))->pluck('dispatch_id', 'id');
        $lines = array_map(fn ($l) => $l + ['dn' => $byItem[$l['dispatch_item_id']] ?? null], $lines);

        return view('finance::invoices.form', $this->formData($invoice, $invoice->only(['customer_id', 'source_type', 'source_id', 'customer_reference', 'place_of_supply']), $lines) + ['notice' => null]);
    }

    public function update(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        try {
            $service->saveDraft($this->head($request), $this->lines($request), $invoice);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()])->withInput();
        }

        return redirect()->route('invoices.show', $invoice)->with('success', 'Draft saved.');
    }

    /** Chooses the print layout for one invoice. Printing only, so it is allowed on an issued invoice too. */
    public function template(Request $request, Invoice $invoice, InvoiceTemplates $templates): RedirectResponse
    {
        $d = $request->validate(['print_template' => ['nullable', Rule::in(array_keys($templates->options($invoice->isCreditNote())))]]);
        $invoice->update(['print_template' => $d['print_template'] ?? null]);

        return back()->with('success', 'Print layout saved.');
    }

    public function issue(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        try {
            $invoice = $service->issue($invoice);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }
        if ($request->boolean('print')) {
            return redirect()->route('invoices.print', $invoice);
        }

        return back()->with('success', "{$invoice->invoice_number} issued.");
    }

    public function void(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);
        try {
            $service->void($invoice, $d['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('success', "{$invoice->invoice_number} voided.");
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== 'DRAFT') {
            return back()->withErrors(['invoice' => 'Only a draft can be deleted; void an issued invoice instead.']);
        }
        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'Draft deleted.');
    }

    /** The credit note form for an invoice, with its lines ready to trim. */
    public function credit(Invoice $invoice): View|RedirectResponse
    {
        if ($invoice->status !== 'ISSUED' || $invoice->isCreditNote()) {
            return redirect()->route('invoices.show', $invoice)->withErrors(['invoice' => 'A credit note can only be raised against an issued invoice.']);
        }
        $invoice->load(['lines', 'customer']);

        return view('finance::invoices.credit', ['invoice' => $invoice, 'taxRates' => TaxRate::where('is_active', true)->orderBy('name')->get()]);
    }

    public function storeCredit(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:400'], 'reason_type' => ['required', Rule::in(['Quality reject', 'Price correction', 'Short shipment', 'Return for credit'])]]);
        try {
            $note = $service->creditNote($invoice, $this->lines($request), $d['reason_type'].': '.$d['reason'], $this->adjustments($request));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()])->withInput();
        }

        return redirect()->route('invoices.show', $note)->with('success', "Credit note {$note->invoice_number} issued against {$invoice->invoice_number}.");
    }

    /** @return array<string, mixed> */
    private function formData(?Invoice $invoice, array $head, array $lines): array
    {
        $service = app(InvoiceService::class);

        $dispatches = $this->billableDispatches($head['customer_id'] ?? null, $invoice, $service);
        $onLines = array_merge(array_column($lines, 'product_id'), collect($dispatches)->pluck('lines')->flatten(1)->pluck('product_id')->all());
        $customer = ($head['customer_id'] ?? null) ? Customer::find($head['customer_id']) : null;

        return [
            'dispatches' => $dispatches, 'customer' => $customer, 'nextNumber' => $invoice?->invoice_number ?: $service->nextNumber('INVOICE'),
            'template' => app(InvoiceTemplates::class)->options()[app(InvoiceTemplates::class)->default()] ?? 'Standard invoice',
            'invoice' => $invoice, 'head' => $head, 'lines' => $lines,
            'customers' => Customer::orderedByName()->get(), 'taxRates' => TaxRate::where('is_active', true)->orderBy('name')->get(),
            'currency' => $invoice?->currency ?? FinanceRules::currency(), 'products' => $this->products($onLines),
        ];
    }

    /**
     * The dispatch notes of a customer not yet billed (plus those already on the draft being edited), with the lines each would add.
     *
     * @return list<array{id: string, code: string, date: string, desc: string, so: string, pcs: float, lines: list<array<string, mixed>>}>
     */
    private function billableDispatches(?string $customerId, ?Invoice $invoice, InvoiceService $service): array
    {
        if (! $customerId || ! class_exists(Dispatch::class)) {
            return [];
        }

        return Dispatch::with(['items.salesOrderLine.salesOrder', 'items.salesOrderLine.product', 'items.packingItem.lot.product'])
            ->where('customer_id', $customerId)->where('status', 'DISPATCHED')->orderBy('dispatched_at')->get()
            ->map(function (Dispatch $d) use ($service, $invoice) {
                $lines = $service->linesFromDispatch($d, $invoice?->id);
                if ($lines === []) {
                    return null;
                }
                $orders = $d->items->map(fn ($i) => $i->salesOrderLine?->salesOrder?->order_number)->filter()->unique()->implode(', ');
                $pcs = $d->items->sum('quantity_dispatched');

                return [
                    'id' => $d->id, 'code' => $d->dispatch_number, 'date' => 'Shipped '.($d->dispatched_at ?? $d->created_at)->format('j M'),
                    'desc' => count($lines) === 1 ? trim((string) preg_replace('/ .*/', '', $lines[0]['description'])).' · '.number_format($pcs).' pcs' : count($lines).' lines · '.number_format($pcs).' pcs',
                    'so' => $orders, 'pcs' => (float) $pcs, 'lines' => array_map(fn ($l) => $l + ['dn' => $d->id], $lines),
                ];
            })->filter()->values()->all();
    }

    /**
     * The products a line can be picked from, keyed by id, with what a picked line is filled with: active products, plus any
     * already on the lines. The price is the last one the product was sold at; the form asks the price lists first.
     *
     * @param  list<string|null>  $onLines
     * @return array<string, array{code: string, name: string, uom: string, price: ?float, kg: ?float, supply_type: string, tariff_code: ?string, inventory_class: ?string}>
     */
    private function products(array $onLines): array
    {
        $last = DB::table('sales_order_lines')->whereNotNull('unit_price')->selectRaw('DISTINCT ON (product_id) product_id, unit_price')
            ->orderBy('product_id')->orderByDesc('created_at')->pluck('unit_price', 'product_id');

        return Product::where(fn ($q) => $q->where('active', true)->orWhereIn('id', array_filter($onLines)))->orderBy('code')->get()
            ->mapWithKeys(fn (Product $p) => [$p->id => [
                'code' => (string) $p->code, 'name' => (string) $p->name, 'uom' => $p->base_uom ?: 'pcs',
                'price' => isset($last[$p->id]) ? (float) $last[$p->id] : null, 'kg' => $p->nominal_weight_kg !== null ? (float) $p->nominal_weight_kg : null,
                'supply_type' => $p->supply_type ?: 'GOODS', 'tariff_code' => $p->tariff_code, 'inventory_class' => $p->inventory_class,
            ]])->all();
    }

    /** @return array<string, mixed> */
    private function head(Request $request): array
    {
        $d = $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:customers,id'], 'invoice_date' => ['required', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['nullable', 'string', 'max:10'], 'payment_terms' => ['nullable', 'string', 'max:100'], 'customer_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'], 'source_type' => ['nullable', 'in:manual,dispatch,sales_order'], 'source_id' => ['nullable', 'uuid'],
            'place_of_supply' => ['nullable', 'string', 'max:10'],
        ]);

        return $d + ['adjustments' => $this->adjustments($request)];
    }

    /**
     * Charge and deduction boxes (design 19o); InvoiceService keeps those with an offered account and an amount.
     *
     * @return list<array<string, mixed>>
     */
    private function adjustments(Request $request): array
    {
        return array_values($request->validate([
            'adjustments' => ['nullable', 'array', 'max:6'], 'adjustments.*.kind' => ['required', 'in:charge,deduction'],
            'adjustments.*.account_id' => ['nullable', 'uuid'], 'adjustments.*.amount' => ['nullable', 'numeric', 'min:0'],
        ])['adjustments'] ?? []);
    }

    /** @return list<array<string, mixed>> */
    private function lines(Request $request): array
    {
        return array_values($request->validate([
            'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['nullable', 'uuid', 'exists:products,id'], 'lines.*.description' => ['nullable', 'string', 'max:300'],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0'], 'lines.*.uom' => ['nullable', 'string', 'max:20'], 'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'], 'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'], 'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_rate_id' => ['nullable', 'uuid', 'exists:tax_rates,id'], 'lines.*.sales_order_line_id' => ['nullable', 'uuid'], 'lines.*.dispatch_item_id' => ['nullable', 'uuid'],
            'lines.*.supply_type' => ['nullable', 'in:GOODS,SERVICE'], 'lines.*.tariff_code' => ['nullable', 'string', 'max:20'], 'lines.*.inventory_class' => ['nullable', 'string', 'max:20'],
        ])['lines']);
    }
}
