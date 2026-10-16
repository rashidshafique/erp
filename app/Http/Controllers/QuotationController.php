<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Services\Quotation\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function __construct(private QuotationService $svc)
    {
    }

    public function store(Enquiry $enquiry)
    {
        abort_unless($enquiry->isOpen(), 422);
        try {
            $q = $this->svc->create($enquiry);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('quotations.show', $q);
    }

    private function load(Quotation $q): Quotation
    {
        if ($q->status === 'ISSUED' && $q->isExpired()) {
            $q->update(['status' => 'EXPIRED']);
        }

        return $q->load('lines.item.analysis', 'lines.item.product:id,code,name', 'enquiry.customer', 'salesOrder:id,order_number');
    }

    public function show(Quotation $quotation)
    {
        $quotation = $this->load($quotation);

        return view('quotations.show', [
            'q' => $quotation, 'blockers' => $quotation->status === 'DRAFT' ? $this->svc->issueBlockers($quotation) : [],
            'versions' => $quotation->enquiry->quotations()->get(['id', 'code', 'version', 'status']),
        ]);
    }

    public function print(Quotation $quotation)
    {
        abort_unless(in_array($quotation->status, ['ISSUED', 'ACCEPTED', 'EXPIRED', 'SUPERSEDED', 'REJECTED'], true), 404, 'Issue the quotation before printing it for the customer.');

        return view('quotations.print', ['q' => $this->load($quotation)]);
    }

    public function update(Request $request, Quotation $quotation)
    {
        abort_unless($quotation->isEditable(), 422);
        $d = $request->validate([
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'payment_terms' => ['nullable', 'string', 'max:200'],
            'delivery_weeks' => ['nullable', 'integer', 'min:1', 'max:104'],
            'tooling_mode' => ['required', Rule::in(['NONE', 'UPFRONT', 'AMORTISED'])],
            'tooling_amortise_qty' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'lines' => ['array'],
            'lines.*.margin_pct' => ['nullable', 'numeric', 'min:0', 'max:80'],
            'lines.*.scrap_pct' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'lines.*.qty' => ['nullable', 'integer', 'min:1'],
            'lines.*.price_override' => ['nullable', 'numeric', 'min:0'],
        ]);
        $quotation->update(collect($d)->except('lines')->all());
        foreach ($d['lines'] ?? [] as $id => $in) {
            $line = QuotationLine::where('quotation_id', $quotation->id)->find($id);
            if (! $line) {
                continue;
            }
            $line->update([
                'margin_pct' => $in['margin_pct'] ?? $line->margin_pct, 'scrap_pct' => $in['scrap_pct'] ?? $line->scrap_pct,
                'qty' => $in['qty'] ?? $line->qty, 'price_override' => ($in['price_override'] ?? '') === '' ? null : $in['price_override'],
            ]);
        }
        $this->svc->recalc($quotation->fresh('lines'));

        return back()->with('success', 'Quotation saved and recalculated.');
    }

    public function refresh(Quotation $quotation)
    {
        abort_unless($quotation->isEditable(), 422);
        try {
            $this->svc->refreshFromItems($quotation);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Weights, yield, cores and scrap re-read from the enquiry. Margins and price overrides kept.');
    }

    public function issue(Quotation $quotation)
    {
        $msg = $this->svc->issue($quotation);

        return back()->with($msg ? 'error' : 'success', $msg ?? 'Quotation issued. Print the customer copy from this page.');
    }

    public function reject(Request $request, Quotation $quotation)
    {
        $d = $request->validate(['decision_note' => ['nullable', 'string', 'max:500']]);
        $this->svc->reject($quotation, $d['decision_note'] ?? null);

        return back()->with('success', 'Marked as rejected by the customer. Create a new version to re-quote.');
    }

    public function accept(Quotation $quotation)
    {
        try {
            $order = $this->svc->accept($quotation);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales-orders.show', $order)->with('success', 'Quotation accepted. A draft sales order was created; the contract review will hold it until the revision is approved.');
    }
}
