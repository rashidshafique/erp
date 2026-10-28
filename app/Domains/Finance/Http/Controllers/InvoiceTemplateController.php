<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Core\Contracts\TaxCalculator;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceLine;
use App\Domains\Finance\Models\InvoiceTemplate;
use App\Domains\Finance\Services\InvoiceLayouts;
use App\Domains\Finance\Services\InvoiceTemplates;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** The template builder: layouts of the printed invoice kept as data, made from a ready-made one or from nothing. */
class InvoiceTemplateController extends Controller
{
    public function index(InvoiceTemplates $templates): View
    {
        return view('finance::invoice-templates.index', [
            'templates' => InvoiceTemplate::orderBy('name')->get(), 'default' => $templates->default(),
            'inUse' => Invoice::whereNotNull('print_template')->where('print_template', 'like', 'custom:%')->pluck('print_template')->flip(),
        ]);
    }

    /** A new template, starting from a ready-made one (?from=TAX) or another custom one (?from=custom:<id>), else the simple layout. */
    public function create(Request $request, InvoiceLayouts $layouts): View
    {
        $from = (string) $request->query('from');
        $source = str_starts_with($from, 'custom:') ? InvoiceTemplate::find(substr($from, 7)) : null;
        $layout = $source?->layout ?? $layouts->builtIn($from) ?? $layouts->builtIn('SIMPLE');
        $name = $source ? $source->name.' (copy)' : ($layouts->builtIn($from) ? app(InvoiceTemplates::class)->options()[$from].' (copy)' : '');

        return $this->form(new InvoiceTemplate(['name' => $name, 'applies_to' => $source?->applies_to ?? 'BOTH', 'layout' => $layout]));
    }

    public function store(Request $request): RedirectResponse
    {
        $template = new InvoiceTemplate(['created_by' => Auth::id(), 'is_active' => true]);
        if ($failure = $this->fill($request, $template)) {
            return $failure;
        }

        return redirect()->route('invoice-templates.edit', $template)->with('success', 'Template saved.');
    }

    public function edit(InvoiceTemplate $template): View
    {
        return $this->form($template);
    }

    public function update(Request $request, InvoiceTemplate $template): RedirectResponse
    {
        if ($failure = $this->fill($request, $template)) {
            return $failure;
        }

        return back()->with('success', 'Template saved.');
    }

    public function toggle(InvoiceTemplate $template): RedirectResponse
    {
        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('success', $template->is_active ? 'Template switched on.' : 'Template switched off. Invoices using it print with the default.');
    }

    public function destroy(InvoiceTemplate $template): RedirectResponse
    {
        if ($template->isInUse()) {
            return back()->withErrors(['template' => 'Invoices use this template, so it can only be switched off.']);
        }
        $template->delete();

        return redirect()->route('invoice-templates.index')->with('success', 'Template deleted.');
    }

    /** Draws the layout as typed (not yet saved) over a real invoice, or a sample one, for the preview beside the builder. */
    public function preview(Request $request, InvoiceLayouts $layouts): View|\Illuminate\Http\Response
    {
        try {
            $layout = $layouts->normalize((array) json_decode((string) $request->input('layout'), true));
        } catch (InvalidArgumentException $e) {
            return response('<p style="font:14px system-ui;color:#b00;padding:16px">'.e($e->getMessage()).'</p>');
        }
        $invoice = $request->filled('invoice') ? Invoice::with(['customer', 'lines', 'original'])->find($request->input('invoice')) : null;
        $invoice ??= $this->sample();

        return view('finance::invoices.print', ['invoice' => $invoice, 'templateView' => InvoiceTemplates::LAYOUT_VIEW, 'layout' => $layout,
            'registrations' => $invoice->exists ? app(TaxCalculator::class)->registrations($invoice->customer?->party_id) : ['seller' => ['NTN' => '1234567-8'], 'party' => ['NTN' => '7654321-0']]]);
    }

    private function form(InvoiceTemplate $template): View
    {
        return view('finance::invoice-templates.form', [
            'template' => $template, 'layouts' => app(InvoiceLayouts::class),
            'recent' => Invoice::with('customer')->whereNotNull('invoice_number')->latest('invoice_date')->limit(15)->get(),
        ]);
    }

    /** Saves the typed template, or returns the redirect that shows what is wrong with it. */
    private function fill(Request $request, InvoiceTemplate $template): ?RedirectResponse
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'applies_to' => ['required', Rule::in(array_keys(InvoiceTemplate::APPLIES))], 'layout' => ['required', 'string']]);
        try {
            $layout = app(InvoiceLayouts::class)->normalize((array) json_decode($d['layout'], true));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['layout' => $e->getMessage()])->withInput();
        }
        $template->fill(['name' => $d['name'], 'applies_to' => $d['applies_to'], 'layout' => $layout])->save();

        return null;
    }

    /** An invoice that is not saved, with a few lines, to see a layout against when no real one is picked. */
    private function sample(): Invoice
    {
        $invoice = new Invoice(['invoice_number' => 'INV-2026-0001', 'invoice_date' => today(), 'due_date' => today()->addDays(30), 'currency' => 'PKR', 'payment_terms' => 'Net 30',
            'customer_reference' => 'PO 4411', 'status' => 'ISSUED', 'kind' => 'INVOICE', 'subtotal' => 1900, 'tax_total' => 342, 'total' => 2242, 'tax_withheld' => 0]);
        $customer = new Customer(['name' => 'Sample Pumps Ltd', 'billing_address' => "12 Industrial Estate\nLahore"]);
        $lines = collect([['Pump casing', 10, 'pcs', 150, 0, 1500, '7325.1000'], ['Impeller', 4, 'pcs', 100, 0, 400, '8413.9100']])->map(fn ($l, $i) => new InvoiceLine([
            'line_number' => $i + 1, 'description' => $l[0], 'quantity' => $l[1], 'uom' => $l[2], 'unit_price' => $l[3], 'discount_percent' => $l[4], 'line_net' => $l[5],
            'tax_percent' => 18, 'line_tax' => round($l[5] * 0.18, 2), 'line_withheld' => 0, 'tariff_code' => $l[6], 'supply_type' => 'GOODS']));
        $invoice->setRelation('customer', $customer)->setRelation('lines', $lines)->setRelation('original', null);

        return $invoice;
    }
}
