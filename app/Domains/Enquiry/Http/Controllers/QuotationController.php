<?php

namespace App\Domains\Enquiry\Http\Controllers;

use App\Domains\Sales\Services\SalesOrderStatus;
use App\Services\Orders\ProductionOrderFactory;
use App\Http\Controllers\Controller;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Enquiry\Models\QuotationEvent;
use App\Domains\Enquiry\Models\QuotationLine;
use App\Services\Patterns\PatternOwnership;
use App\Domains\Enquiry\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function __construct(private QuotationService $svc) {}

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

    /** A draft opens the costing editor (1d) where its price is worked out; every other revision, and a draft asked for as ?record, opens the record (30b). */
    public function show(Request $request, Quotation $quotation)
    {
        $quotation = $this->load($quotation);
        if ($quotation->status === 'DRAFT' && ! $request->boolean('record')) {
            return view('enquiry::quotations.show', [
                'q' => $quotation, 'blockers' => $this->svc->issueBlockers($quotation),
                'versions' => $quotation->enquiry->quotations()->get(['id', 'code', 'version', 'status']),
                'toolingNotes' => $quotation->lines->mapWithKeys(fn ($l) => [$l->id => app(PatternOwnership::class)->toolingFor($quotation->enquiry->customer_id, $l->item?->product_id)]),
            ]);
        }

        return view('enquiry::quotations.record', [
            'q' => $quotation, 'revisions' => $quotation->revisions()->with('lines.item')->get(), 'events' => $quotation->events()->get(),
            'floor' => (float) \App\Domains\Enquiry\Services\Rates::get()['min_margin_pct'], 'isLatest' => $quotation->isLatest(),
        ]);
    }

    /** The new revision form (design 30c). */
    public function reviseForm(Quotation $quotation)
    {
        abort_unless($this->svc->canRevise($quotation), 422);
        $quotation = $this->load($quotation);

        return view('enquiry::quotations.revise', [
            'q' => $quotation, 'costToday' => $this->svc->costToday($quotation), 'floor' => (float) \App\Domains\Enquiry\Services\Rates::get()['min_margin_pct'],
            'validityDays' => (int) config('quotation.validity_days'),
        ]);
    }

    /** Makes the next revision, and sends it too when asked (it may then wait for approval). */
    public function reviseStore(Request $request, Quotation $quotation)
    {
        abort_unless($this->svc->canRevise($quotation), 422);
        $d = $request->validate([
            'reason' => ['required', Rule::in(array_keys(Quotation::REVISION_REASONS))],
            'changes' => ['array'], 'changes.*' => [Rule::in(['price', 'qty', 'tooling', 'lead', 'scope', 'terms'])],
            'lines' => ['array'], 'lines.*.apply' => ['nullable', 'boolean'], 'lines.*.price' => ['nullable', 'numeric', 'min:0.01'],
            'lines.*.annual_qty' => ['nullable', 'integer', 'min:1'], 'lines.*.tooling' => ['nullable', 'numeric', 'min:0'], 'lines.*.family' => ['nullable', Rule::in(['GREY', 'DUCTILE'])],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'], 'notes' => ['nullable', 'string', 'max:3000'],
            'delivery_weeks' => ['nullable', 'integer', 'min:1', 'max:104'], 'payment_terms' => ['nullable', 'string', 'max:200'],
            'action' => ['required', Rule::in(['send', 'draft'])],
        ]);
        $revision = $this->svc->revise($quotation, $d);
        if ($d['action'] === 'send') {
            $msg = $this->svc->issue($revision);

            return redirect()->route('quotations.show', [$revision, 'record' => 1])->with($msg ? 'error' : 'success', $msg ?? "Revision {$revision->version} sent.");
        }

        return redirect()->route('quotations.show', $revision)->with('success', "Revision {$revision->version} saved as a draft. It keeps the number {$revision->code}.");
    }

    /** The margin approval (design 30d): the gap in money, what changed since the last revision, what the part has fetched before, then the decision. */
    public function approvalForm(Quotation $quotation)
    {
        $quotation = $this->load($quotation);
        $floor = (float) \App\Domains\Enquiry\Services\Rates::get()['min_margin_pct'];
        $line = $quotation->lines->sortBy('effective_margin_pct')->first();
        $floorPrice = $line && (float) $line->unit_cost > 0 ? (float) $line->unit_cost / (1 - $floor / 100) : 0.0;
        $gap = $line ? max(0.0, $floorPrice - (float) $line->unit_price) : 0.0;
        $productId = $line?->item?->product_id;

        return view('enquiry::quotations.approval', [
            'q' => $quotation, 'floor' => $floor, 'line' => $line, 'gap' => $gap, 'gapYear' => $gap * (float) ($line?->annualQty() ?: $line?->qty ?: 0),
            'previous' => $quotation->revisions()->with('lines')->where('version', '<', $quotation->version)->first(),
            'earlier' => $quotation->revisions()->with('lines')->where('version', '<', $quotation->version)->get(),
            'orders' => $productId ? \App\Domains\Sales\Models\SalesOrderLine::query()->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                ->where('sales_order_lines.product_id', $productId)->orderByDesc('sales_orders.order_date')->limit(3)
                ->get(['sales_orders.order_number', 'sales_orders.order_date', 'sales_order_lines.unit_price', 'sales_order_lines.ordered_qty']) : collect(),
            'note' => $quotation->events()->where('kind', 'NOTE')->first(), 'request' => $this->svc->pendingApproval($quotation),
            'canDecide' => (bool) request()->user()?->hasPermission('approvals.decide') || ! config('foundry.require_login'),
        ]);
    }

    public function approvalDecide(Request $request, Quotation $quotation)
    {
        $d = $request->validate(['choice' => ['required', Rule::in(['approve', 'condition', 'back'])], 'note' => ['nullable', 'string', 'max:1000'], 'condition' => ['nullable', 'string', 'max:500']]);
        try {
            $this->svc->decideApproval($quotation, $d['choice'], $d['note'] ?? null, $d['condition'] ?? null, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }
        $msg = ['approve' => 'Approved. The quotation is ready to send.', 'condition' => 'Approved with a condition. It can be sent once the condition is met.', 'back' => 'Sent back to the quote owner with your note.'][$d['choice']];

        return redirect()->route('quotations.show', [$quotation, 'record' => 1])->with('success', $msg);
    }

    public function conditionMet(Quotation $quotation)
    {
        $this->svc->conditionMet($quotation);

        return back()->with('success', 'Condition marked as met.');
    }

    /** A line the team adds to the log: an email in or out, or a note. */
    public function log(Request $request, Quotation $quotation)
    {
        $d = $request->validate(['kind' => ['required', Rule::in(['EMAIL_OUT', 'EMAIL_IN', 'NOTE'])], 'text' => ['required', 'string', 'max:1000']]);
        QuotationEvent::log($quotation, str_starts_with($d['kind'], 'EMAIL') ? 'EMAIL' : 'NOTE', $d['text'], $d['kind'] === 'EMAIL_OUT' ? 'OUT' : ($d['kind'] === 'EMAIL_IN' ? 'IN' : null));

        return back()->with('success', 'Added to the log.');
    }

    public function print(Quotation $quotation)
    {
        abort_unless(in_array($quotation->status, ['ISSUED', 'ACCEPTED', 'EXPIRED', 'SUPERSEDED', 'REJECTED'], true), 404, 'Issue the quotation before printing it for the customer.');

        return view('enquiry::quotations.print', ['q' => $this->load($quotation)]);
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

        if (! Route::has('sales-orders.show')) {
            return redirect()->route('quotations.show', $quotation)->with('success', "Quotation accepted. Draft sales order {$order->order_number} was created.");
        }

        // New products come with a draft revision, which the order waits for; repeat work goes on as any entered order would.
        if ($order->lines()->whereHas('productRevision', fn ($q) => $q->whereNotIn('status', ['APPROVED', 'ACTIVE']))->exists()) {
            return redirect()->route('sales-orders.show', $order)->with('success', 'Quotation accepted. A draft sales order was created; the contract review will hold it until the revision is approved.');
        }
        $done = app(SalesOrderStatus::class)->afterEntry($order);

        return redirect()->route('sales-orders.show', $order)->with('success', "Quotation accepted. Sales order {$order->order_number} {$done}.")
            ->with('warning', ProductionOrderFactory::awaitingApprovalNote($order));
    }
}
