<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\TaxRate;
use App\Domains\Finance\Services\FinanceRules;
use App\Domains\Finance\Services\InvoiceService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->filled('customer') && Str::isUuid($request->query('customer')) ? $request->query('customer') : null;
        $status = in_array($request->query('status'), ['DRAFT', 'ISSUED', 'VOID', 'OPEN', 'OVERDUE'], true) ? $request->query('status') : null;
        $query = Invoice::with('customer')->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when(in_array($status, ['DRAFT', 'ISSUED', 'VOID'], true), fn ($q) => $q->where('status', $status))
            ->when(in_array($status, ['OPEN', 'OVERDUE'], true), fn ($q) => $q->where('status', 'ISSUED')->where('kind', 'INVOICE'))
            ->orderByDesc('invoice_date')->orderByDesc('created_at');
        $rows = $status === 'OPEN' || $status === 'OVERDUE'
            ? $query->get()->filter(fn (Invoice $i) => $status === 'OPEN' ? $i->balance() > 0 : $i->isOverdue())->values()
            : $query->paginate(25)->withQueryString();

        return view('finance::invoices.index', [
            'invoices' => $rows, 'customerId' => $customerId, 'status' => $status,
            'customers' => Customer::whereIn('id', Invoice::select('customer_id'))->orderedByName()->get(),
        ]);
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
        $checks = $invoice->status === 'DRAFT' && ! $invoice->isCreditNote() ? app(InvoiceService::class)->taxChecks($invoice) : ['blocks' => [], 'warnings' => []];

        return view('finance::invoices.show', ['invoice' => $invoice, 'taxChecks' => $checks]);
    }

    public function print(Invoice $invoice): View
    {
        $invoice->load(['customer', 'lines', 'original']);

        return view('finance::invoices.print', ['invoice' => $invoice, 'registrations' => app(\App\Domains\Core\Contracts\TaxCalculator::class)->registrations($invoice->customer?->party_id)]);
    }

    public function edit(Invoice $invoice): View|RedirectResponse
    {
        if ($invoice->status !== 'DRAFT') {
            return redirect()->route('invoices.show', $invoice)->withErrors(['invoice' => 'Only a draft can be changed.']);
        }
        $lines = $invoice->lines->map(fn ($l) => $l->only(['description', 'quantity', 'uom', 'unit_price', 'tax_rate_id', 'sales_order_line_id', 'dispatch_item_id', 'supply_type', 'tariff_code', 'inventory_class']))->all();

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

    public function issue(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        try {
            $invoice = $service->issue($invoice);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
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
        $d = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        try {
            $note = $service->creditNote($invoice, $this->lines($request), $d['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()])->withInput();
        }

        return redirect()->route('invoices.show', $note)->with('success', "Credit note {$note->invoice_number} issued against {$invoice->invoice_number}.");
    }

    /** @return array<string, mixed> */
    private function formData(?Invoice $invoice, array $head, array $lines): array
    {
        $service = app(InvoiceService::class);

        return [
            'invoice' => $invoice, 'head' => $head, 'lines' => $lines ?: [['description' => '', 'quantity' => 1, 'uom' => '', 'unit_price' => 0, 'tax_rate_id' => $service->defaultTaxRateId(), 'sales_order_line_id' => null, 'dispatch_item_id' => null]],
            'customers' => Customer::orderedByName()->get(), 'taxRates' => TaxRate::where('is_active', true)->orderBy('name')->get(),
            'currency' => $invoice?->currency ?? FinanceRules::currency(),
        ];
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

        return $d;
    }

    /** @return list<array<string, mixed>> */
    private function lines(Request $request): array
    {
        return array_values($request->validate([
            'lines' => ['required', 'array', 'min:1'], 'lines.*.description' => ['nullable', 'string', 'max:300'],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0'], 'lines.*.uom' => ['nullable', 'string', 'max:20'], 'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate_id' => ['nullable', 'uuid', 'exists:tax_rates,id'], 'lines.*.sales_order_line_id' => ['nullable', 'uuid'], 'lines.*.dispatch_item_id' => ['nullable', 'uuid'],
            'lines.*.supply_type' => ['nullable', 'in:GOODS,SERVICE'], 'lines.*.tariff_code' => ['nullable', 'string', 'max:20'], 'lines.*.inventory_class' => ['nullable', 'string', 'max:20'],
        ])['lines']);
    }
}
