<?php

namespace App\Domains\Enquiry\Services;

use App\Domains\Core\ModuleRegistry;
use App\Domains\Development\Services\DevelopmentOpener;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryEvent;
use App\Domains\Enquiry\Models\EnquiryItem;
use App\Domains\Enquiry\Models\QuotationEvent;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\Approvals\ApprovalDecided;
use App\Services\Approvals\ApprovalGate;
use App\Services\Patterns\PatternOwnership;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(private QuoteCalculator $calc = new QuoteCalculator) {}

    /**
     * What the cost build-up needs for one item. Weights come from the current revision of the pattern that makes the product
     * when it has them recorded, else from the linked analysis, else from what was typed. Superseded revisions are never read.
     */
    public function inputsFor(EnquiryItem $item, array $rates): array
    {
        $a = $item->analysis;
        $qty = (int) ($item->first_batch_qty ?: $item->annual_qty ?: 1);
        if ($revision = $this->currentPatternRevisionFor($item)) {
            $weight = (float) $revision->casting_weight_kg;
            $poured = (float) $revision->pour_weight_kg;
            $scrap = (float) $rates['scrap_default_pct'];

            return [
                'qty' => $qty, 'weight_kg' => $weight, 'poured_kg' => $poured, 'family' => $item->family ?: config('quotation.default_family'),
                'cores_count' => (int) ($item->cores_count ?? 0), 'scrap_pct' => $scrap,
                'basis' => sprintf('From pattern revision %s (current): casting %.2f kg, poured %.2f kg (yield %.0f%%), scrap assumed %.0f%%.', $revision->code, $weight, $poured, $revision->yieldPercent(), $scrap),
            ];
        }
        if ($a && $a->status === 'COMPLETE' && $a->recommendation) {
            $w = $a->recommendation['weight'];
            $weight = (float) $w['casting_kg'];
            $poured = (float) $w['poured_kg'];
            $scrap = Rates::scrapForScore($a->score, $rates);
            $basis = sprintf('From analysis %s: casting %.2f kg, poured %.2f kg (yield %.0f%%), score %d gives %.0f%% scrap.', $a->code, $weight, $poured, $w['yield_pct'], $a->score, $scrap);

            return [
                'qty' => $qty, 'weight_kg' => $weight, 'poured_kg' => $poured, 'family' => $a->family ?: ($item->family ?: config('quotation.default_family')),
                'cores_count' => (int) ($a->recommendation['tooling']['core_count'] ?? 0), 'scrap_pct' => $scrap, 'basis' => $basis,
            ];
        }
        $weight = (float) $item->weight_kg;
        if ($weight <= 0) {
            throw new \RuntimeException("Item {$item->line_no} ({$item->description}) has no weight. Link a casting analysis or enter an estimated weight.");
        }
        $yield = $rates['default_yield_pct'];

        return [
            'qty' => $qty, 'weight_kg' => $weight, 'poured_kg' => $weight / ($yield / 100), 'family' => $item->family ?: config('quotation.default_family'),
            'cores_count' => (int) ($item->cores_count ?? 0), 'scrap_pct' => (float) $rates['scrap_default_pct'],
            'basis' => sprintf('No casting analysis: weight %.2f kg entered by hand, yield assumed %.0f%%, scrap assumed %.0f%%.', $weight, $yield, $rates['scrap_default_pct']),
        ];
    }

    /** A new draft revision of the enquiry's quotation, priced from its items. It keeps the quotation's number; an issued revision is superseded when this one is sent. */
    public function create(Enquiry $enquiry): Quotation
    {
        $rates = Rates::get();
        $enquiry->load('items.analysis', 'quotations.lines');
        if ($enquiry->items->isEmpty()) {
            throw new \RuntimeException('Add at least one item to the enquiry before quoting.');
        }
        $draft = $enquiry->quotations->firstWhere('status', 'DRAFT');
        if ($draft) {
            return $draft;
        }
        $inputs = [];
        foreach ($enquiry->items as $item) {
            $inputs[$item->id] = $this->inputsFor($item, $rates);
        }
        $prev = $enquiry->quotations->sortByDesc('version')->first();

        return DB::transaction(function () use ($enquiry, $rates, $inputs, $prev) {
            $q = Quotation::create([
                'code' => $prev?->code, 'enquiry_id' => $enquiry->id, 'version' => ($prev?->version ?? 0) + 1, 'status' => 'DRAFT', 'currency' => config('quotation.currency'),
                'payment_terms' => $prev?->payment_terms ?? $enquiry->customer?->payment_terms, 'delivery_weeks' => $prev?->delivery_weeks,
                'tooling_mode' => $prev?->tooling_mode ?? 'NONE', 'tooling_amortise_qty' => $prev?->tooling_amortise_qty, 'notes' => $prev?->notes,
                'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name,
            ]);
            foreach ($enquiry->items as $i => $item) {
                $in = $inputs[$item->id];
                $tooling = app(PatternOwnership::class)->toolingFor($enquiry->customer_id, $item->product_id);
                $old = $prev?->lines->firstWhere('enquiry_item_id', $item->id);
                $line = $q->lines()->create([
                    'enquiry_item_id' => $item->id, 'line_no' => $i + 1, 'qty' => $in['qty'], 'annual_qty' => $item->annual_qty ?: null, 'weight_kg' => $in['weight_kg'], 'poured_kg' => $in['poured_kg'], 'family' => $in['family'],
                    'cores_count' => $in['cores_count'], 'heat_treatment' => $item->heat_treatment, 'ndt' => $item->ndt,
                    'machining_cost' => $item->machining_cost, 'other_cost' => $item->other_cost, 'scrap_pct' => $in['scrap_pct'],
                    'overhead_pct' => $rates['overhead_pct'], 'margin_pct' => $old?->margin_pct ?? $rates['margin_pct'], 'tooling_cost' => $tooling['customer_owned'] ? 0 : $item->tooling_cost,
                    'price_override' => $old?->price_override, 'basis' => $in['basis'],
                ]);
            }
            $this->recalc($q->fresh('lines'));

            return $q->fresh('lines');
        });
    }

    /** The revisions a quotation can still be revised from: a closed-out accepted one is locked, later changes go on the order. */
    public function canRevise(Quotation $q): bool
    {
        return in_array($q->status, ['ISSUED', 'EXPIRED', 'REJECTED'], true) && $q->isLatest();
    }

    /**
     * The next revision of a quotation (design 30c), as a draft with the same number. Lines ticked to change take the new figures, the
     * others keep exactly the price they had; the earlier revision is marked superseded and stays on the record. The change note says
     * what moved, so the list of revisions answers "why" six months later.
     *
     * @param  array{reason: string, changes: list<string>, lines?: array<string, array{apply?: bool, price?: mixed, annual_qty?: mixed, tooling?: mixed, family?: ?string}>, valid_until?: ?string, notes?: ?string, delivery_weeks?: mixed, payment_terms?: ?string}  $in
     */
    public function revise(Quotation $from, array $in): Quotation
    {
        abort_unless($this->canRevise($from), 422);
        $changes = $in['changes'] ?? [];
        $from->load('lines.item');

        return DB::transaction(function () use ($from, $in, $changes) {
            $moved = [];
            $q = Quotation::create([
                'code' => $from->code, 'enquiry_id' => $from->enquiry_id, 'version' => $from->version + 1, 'status' => 'DRAFT', 'currency' => $from->currency,
                'payment_terms' => in_array('terms', $changes, true) && filled($in['payment_terms'] ?? null) ? $in['payment_terms'] : $from->payment_terms,
                'delivery_weeks' => in_array('lead', $changes, true) && filled($in['delivery_weeks'] ?? null) ? (int) $in['delivery_weeks'] : $from->delivery_weeks,
                'tooling_mode' => $from->tooling_mode, 'tooling_amortise_qty' => $from->tooling_amortise_qty,
                'valid_until' => $in['valid_until'] ?? null, 'notes' => $in['notes'] ?? null, 'revision_reason' => $in['reason'],
                'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name,
            ]);
            foreach ($from->lines as $old) {
                $row = $in['lines'][$old->id] ?? [];
                $apply = (bool) ($row['apply'] ?? false);
                $new = $old->only(['enquiry_item_id', 'line_no', 'qty', 'annual_qty', 'weight_kg', 'poured_kg', 'family', 'cores_count', 'heat_treatment', 'ndt', 'machining_cost', 'other_cost', 'scrap_pct', 'overhead_pct', 'margin_pct', 'tooling_cost', 'basis']);
                $new['price_override'] = $old->unit_price;
                if ($apply && in_array('price', $changes, true) && filled($row['price'] ?? null) && round((float) $row['price'], 2) !== round((float) $old->unit_price, 2)) {
                    $new['price_override'] = round((float) $row['price'], 2);
                    $moved[] = sprintf('piece price %s to %s', \App\Support\Formats::number((float) $old->unit_price, 2), \App\Support\Formats::number((float) $new['price_override'], 2));
                }
                if ($apply && in_array('qty', $changes, true) && filled($row['annual_qty'] ?? null) && (int) $row['annual_qty'] !== (int) $old->annualQty()) {
                    $new['annual_qty'] = (int) $row['annual_qty'];
                    $moved[] = sprintf('annual quantity %s to %s', \App\Support\Formats::number((int) $old->annualQty()), \App\Support\Formats::number($new['annual_qty']));
                }
                if ($apply && in_array('tooling', $changes, true) && filled($row['tooling'] ?? null) && round((float) $row['tooling'], 2) !== round((float) $old->tooling_cost, 2)) {
                    $new['tooling_cost'] = round((float) $row['tooling'], 2);
                    $moved[] = 'tooling changed';
                }
                if ($apply && in_array('scope', $changes, true) && filled($row['family'] ?? null) && $row['family'] !== $old->family) {
                    $new['family'] = $row['family'];
                    $moved[] = 'alloy changed to '.strtolower($row['family']);
                }
                $q->lines()->create($new);
            }
            if (in_array('lead', $changes, true) && (int) $q->delivery_weeks !== (int) $from->delivery_weeks) {
                $moved[] = "lead time {$from->delivery_weeks} to {$q->delivery_weeks} weeks";
            }
            if (in_array('terms', $changes, true) && $q->payment_terms !== $from->payment_terms) {
                $moved[] = 'terms changed';
            }
            $this->recalc($q->fresh('lines'));
            $label = Quotation::REVISION_REASONS[$in['reason']][0] ?? 'Revised';
            $q->update(['change_note' => $label.($moved ? ': '.implode(', ', array_unique($moved)) : '')]);
            $from->update(['status' => 'SUPERSEDED']);
            QuotationEvent::log($q, 'SYSTEM', "Rev {$q->version} made from rev {$from->version}. {$q->change_note}.");

            return $q->fresh('lines');
        });
    }

    /**
     * What each line costs a piece at today's rates, for the margin preview on a new revision. Line id => cost.
     *
     * @return array<string, float>
     */
    public function costToday(Quotation $q): array
    {
        $rates = Rates::get();
        $ctx = ['tooling_mode' => $q->tooling_mode, 'tooling_amortise_qty' => $q->tooling_amortise_qty];

        return $q->lines->mapWithKeys(fn ($l) => [$l->id => $this->calc->line(
            $l->only(['weight_kg', 'poured_kg', 'family', 'cores_count', 'heat_treatment', 'ndt', 'machining_cost', 'other_cost', 'scrap_pct', 'overhead_pct', 'margin_pct', 'tooling_cost']) + ['price_override' => null], $rates, $ctx
        )['unit_cost']])->all();
    }

    /** Recompute every line from its stored inputs. */
    public function recalc(Quotation $q): Quotation
    {
        $rates = Rates::get();
        $ctx = ['tooling_mode' => $q->tooling_mode, 'tooling_amortise_qty' => $q->tooling_amortise_qty];
        foreach ($q->lines as $line) {
            $r = $this->calc->line($line->only(['weight_kg', 'poured_kg', 'family', 'cores_count', 'heat_treatment', 'ndt', 'machining_cost', 'other_cost', 'scrap_pct', 'overhead_pct', 'margin_pct', 'tooling_cost', 'price_override']), $rates, $ctx);
            $line->update(['unit_cost' => $r['unit_cost'], 'unit_price' => $r['unit_price'], 'effective_margin_pct' => $r['effective_margin_pct'], 'breakdown' => $r['breakdown'] + ['calculated_price' => $r['calculated_price']]]);
        }

        return $q;
    }

    /** Re-read weights, yield, cores and scrap from the enquiry items, keeping margins and price overrides. */
    public function refreshFromItems(Quotation $q): Quotation
    {
        $rates = Rates::get();
        $q->load('lines.item.analysis');
        foreach ($q->lines as $line) {
            $in = $this->inputsFor($line->item, $rates);
            $line->update(['weight_kg' => $in['weight_kg'], 'poured_kg' => $in['poured_kg'], 'family' => $in['family'], 'cores_count' => $in['cores_count'], 'scrap_pct' => $in['scrap_pct'], 'basis' => $in['basis']]);
        }

        return $this->recalc($q->fresh('lines'));
    }

    /** Reasons the quotation cannot be issued yet; empty when it can. */
    public function issueBlockers(Quotation $q): array
    {
        $q->load('lines.item.analysis');
        $out = [];
        foreach ($q->lines as $line) {
            $item = $line->item;
            $label = "Item {$item->line_no} ({$item->description})";
            if ($item->feasibility_status === 'PENDING') {
                $out[] = "$label has not been assessed for feasibility.";

                continue;
            }
            if ($item->feasibility_status === 'NOT_FEASIBLE') {
                $out[] = "$label is marked not feasible.";

                continue;
            }
            $a = $item->analysis;
            $severe = $a && $a->status === 'COMPLETE' && collect($a->result['hotspots'] ?? [])->contains('role', 'ISOLATED_SEVERE');
            $hard = $a && $a->status === 'COMPLETE' && ($a->score ?? 100) < 55;
            if (($severe || $hard) && $item->feasibility_status !== 'CONDITIONAL') {
                $out[] = "$label: the casting analysis shows ".($severe ? 'a cut-off hotspot' : 'a low manufacturability score ('.$a->score.')').'. Record the decision as "Feasible with conditions" and say how it will be handled.';
            }
            if ($item->feasibility_status === 'CONDITIONAL' && blank($item->feasibility_notes)) {
                $out[] = "$label is feasible with conditions, but the conditions are not written down.";
            }
            if ((float) $line->unit_price <= 0) {
                $out[] = "$label has no price.";
            }
        }
        if ($q->tooling_mode === 'AMORTISED' && (int) $q->tooling_amortise_qty < 1) {
            $out[] = 'Tooling is spread over the castings, but the number of castings is not set.';
        }
        if (filled($q->approval_condition)) {
            $out[] = 'Approved on one condition that is not marked as met: '.$q->approval_condition;
        }

        return $out;
    }

    /** The approval request waiting on this revision, when its margin is under the floor and the approval rule is on. */
    public function pendingApproval(Quotation $q): ?ApprovalRequest
    {
        return ApprovalRequest::where('action_key', 'quotation.issue_low_margin')->where('subject_type', 'Quotation')->where('subject_id', $q->id)->where('status', 'PENDING')->first();
    }

    /**
     * The approver's answer (design 30d): approve, approve with a condition that has to be met before it can be sent, or send it back
     * with a note. The approval engine records and checks the decision; onApprovalDecided moves the quotation on.
     *
     * @throws \RuntimeException when the approver may not decide, or the answer needs a note or condition it was not given
     */
    public function decideApproval(Quotation $q, string $choice, ?string $note, ?string $condition, ?User $user): void
    {
        if ($q->status !== 'AWAITING_APPROVAL') {
            throw new \RuntimeException('This quotation is not waiting for approval.');
        }
        $req = $this->pendingApproval($q) ?? throw new \RuntimeException('There is no approval request waiting for this quotation.');
        if ($choice === 'condition' && blank($condition)) {
            throw new \RuntimeException('Write the condition that has to be met before it can be sent.');
        }
        if ($choice === 'back' && blank($note)) {
            throw new \RuntimeException('Give a reason when sending it back.');
        }
        DB::transaction(function () use ($q, $req, $choice, $note, $condition, $user) {
            if ($choice === 'condition') {
                $q->update(['approval_condition' => $condition]);
            }
            app(ApprovalGate::class)->decide($req, $choice === 'back' ? 'REJECT' : 'APPROVE', $choice === 'condition' ? trim('Approved with a condition: '.$condition.' '.$note) : $note, $user);
        });
    }

    /** Moves a quotation on once its approval request is decided: approved and ready to send, or back to a draft. */
    public function onApprovalDecided(ApprovalDecided $event): void
    {
        $req = $event->request;
        if ($req->action_key !== 'quotation.issue_low_margin' || $req->subject_type !== 'Quotation') {
            return;
        }
        $q = Quotation::with('lines')->find($req->subject_id);
        if (! $q || $q->status !== 'AWAITING_APPROVAL') {
            return;
        }
        $last = $req->decisions()->latest('created_at')->first();
        $who = $last?->user_name ?? 'An approver';
        if ($req->status === 'APPROVED') {
            $q->update(['status' => 'DRAFT', 'approved_by' => $who, 'approved_at' => now()]);
            QuotationEvent::log($q, 'APPROVAL', sprintf('Approved at %.1f%% by %s.', $q->lowestMargin(), $who).(filled($q->approval_condition) ? ' Condition: '.$q->approval_condition : ''));
        } elseif (in_array($req->status, ['REJECTED', 'CANCELLED'], true)) {
            $q->update(['status' => 'DRAFT', 'approval_asked_at' => null, 'approval_condition' => null]);
            QuotationEvent::log($q, 'APPROVAL', $req->status === 'CANCELLED' ? 'The approval request was cancelled. Back to a draft.' : "Sent back by {$who}: ".($last?->comment ?? '').'');
        } else {
            QuotationEvent::log($q, 'APPROVAL', "{$who} approved. Waiting for the rest of the approvals.");
        }
    }

    /** The approver's condition has been met, so the quotation can be sent. The log keeps what it was. */
    public function conditionMet(Quotation $q): void
    {
        abort_unless($q->status === 'DRAFT' && filled($q->approval_condition), 422);
        QuotationEvent::log($q, 'APPROVAL', 'Condition met: '.$q->approval_condition);
        $q->update(['approval_condition' => null]);
    }

    public function lowestMargin(Quotation $q): float
    {
        return (float) $q->lines->min('effective_margin_pct');
    }

    /** Issue the quotation. Returns null when done, or the message to show (blockers or an approval request). */
    public function issue(Quotation $q): ?string
    {
        if ($q->status !== 'DRAFT') {
            return 'Only a draft can be issued.';
        }
        $blockers = $this->issueBlockers($q);
        if ($blockers) {
            return 'Not ready to issue: '.implode(' ', $blockers);
        }
        $min = (float) Rates::get()['min_margin_pct'];
        $low = $this->lowestMargin($q);
        if ($low < $min) {
            $msg = null;
            $ok = app(ApprovalGate::class)->passes('quotation.issue_low_margin', 'Quotation', $q->id, $q->code.sprintf(' rev %d (margin %.1f%% against minimum %.1f%%)', $q->version, $low, $min), $msg);
            if (! $ok) {
                if ($q->status === 'DRAFT') {
                    $q->update(['status' => 'AWAITING_APPROVAL', 'approval_asked_at' => now(), 'approval_asked_by' => Auth::user()?->name]);
                    QuotationEvent::log($q, 'APPROVAL', sprintf('Sent for approval: margin %.1f%% against a floor of %.1f%%.', $low, $min));
                }

                return $msg;
            }
        }
        DB::transaction(function () use ($q) {
            $q->enquiry->quotations()->whereIn('status', ['ISSUED'])->where('id', '!=', $q->id)->update(['status' => 'SUPERSEDED']);
            $q->update([
                'status' => 'ISSUED', 'issued_at' => now(), 'issued_by_name' => Auth::user()?->name,
                'valid_until' => $q->valid_until ?? now()->addDays((int) config('quotation.validity_days'))->toDateString(),
            ]);
            if (in_array($q->enquiry->status, ['NEW', 'FEASIBILITY'], true)) {
                $q->enquiry->update(['status' => 'QUOTED']);
            }
            QuotationEvent::log($q, 'SYSTEM', "Rev {$q->version} sent.");
        });

        return null;
    }

    public function reject(Quotation $q, ?string $reason): void
    {
        abort_unless(in_array($q->status, ['ISSUED', 'EXPIRED'], true), 422);
        $q->update(['status' => 'REJECTED', 'decided_at' => now(), 'decision_note' => $reason]);
        QuotationEvent::log($q, 'SYSTEM', "Rev {$q->version} marked lost".(filled($reason) ? ": {$reason}." : '.'));
    }

    /**
     * The customer said yes. Creates what is missing (product, draft revision) and a DRAFT sales order priced from the quote.
     * The contract review then blocks the order until the revision is approved, so the usual gates still apply.
     */
    public function accept(Quotation $q): SalesOrder
    {
        return DB::transaction(function () use ($q) {
            $q = Quotation::with(['lines.item.analysis', 'enquiry.customer'])->lockForUpdate()->findOrFail($q->id);
            if ($q->status !== 'ISSUED') {
                throw new \RuntimeException('Only an issued quotation can be accepted.');
            }
            if ($q->isExpired()) {
                $q->update(['status' => 'EXPIRED']);
                throw new \RuntimeException('This quotation has expired. Create a new version and reissue it.');
            }
            $enquiry = $q->enquiry;
            $firstTime = (bool) $enquiry->customer?->isFirstTimeCustomer();
            $order = SalesOrder::create([
                'order_number' => SalesOrder::nextNumber(), 'customer_id' => $enquiry->customer_id, 'order_date' => now()->toDateString(),
                'requested_delivery_date' => $q->delivery_weeks ? now()->addWeeks($q->delivery_weeks)->toDateString() : null,
                'customer_reference' => $enquiry->customer_reference ?: $enquiry->code,
                'notes' => trim("From quotation {$q->code} ({$enquiry->code}).".($q->tooling_mode === 'UPFRONT' ? ' Tooling charge agreed: '.\App\Support\Formats::number($q->upfrontToolingPrice(), 2)." {$q->currency}, invoiced separately." : '')), 'status' => 'DRAFT',
            ]);
            foreach ($q->lines as $i => $line) {
                $item = $line->item;
                $existed = (bool) $item->product_id;
                $product = $item->product ?? Product::create([
                    'name' => $item->description, 'product_type' => 'CASTING', 'base_uom' => 'piece', 'nominal_weight_kg' => $line->weight_kg, 'active' => true,
                    'description' => trim(($item->drawing_ref ? 'Drawing '.$item->drawing_ref.($item->drawing_rev ? ' rev '.$item->drawing_rev : '').'. ' : '').'Created from '.$enquiry->code.'.'),
                ]);
                $item->update(['product_id' => $product->id]);
                $revision = $this->revisionFor($product, $existed && $enquiry->source_type === 'CHANGE', (float) $line->weight_kg);
                if ($item->analysis && ! $item->analysis->product_id) {
                    $item->analysis->update(['product_id' => $product->id]);
                }
                $order->lines()->create([
                    'product_id' => $product->id, 'product_revision_id' => $revision->id, 'line_number' => $i + 1, 'ordered_qty' => $line->qty,
                    'unit_price' => $line->unit_price, 'uom' => 'piece', 'requested_delivery_date' => $order->requested_delivery_date,
                ]);
            }
            $q->update(['status' => 'ACCEPTED', 'decided_at' => now(), 'sales_order_id' => $order->id]);
            $enquiry->update(['status' => 'WON', 'closed_at' => now()]);
            QuotationEvent::log($q, 'SYSTEM', "Rev {$q->version} accepted; sales order {$order->order_number} created.");
            $this->openDevelopments($enquiry, $firstTime, $q);

            return $order;
        });
    }

    /** How a lost reason on a quotation reads on the enquiry, whose own list of reasons feeds the lost-and-declined summary. */
    public const ENQUIRY_REASONS = ['PRICE' => 'PRICE', 'LEAD_TIME' => 'DELIVERY_LEAD', 'CAPABILITY' => 'CAPABILITY', 'NO_REPLY' => 'NO_RESPONSE', 'CANCELLED' => 'PROJECT_CANCELLED'];

    /** Whether the quotation is at a point where the customer's answer can be recorded: it has been sent and nothing has been decided. */
    public function canClose(Quotation $q): bool
    {
        return in_array($q->status, ['ISSUED', 'EXPIRED'], true) && $q->isLatest();
    }

    /**
     * Records the customer's answer (design 30e). Accepted fills a sales order from the quote, closes the enquiry as won and keeps their
     * order reference; lost asks why, which feeds the lost-and-declined figures; no reply keeps the history without calling it lost.
     *
     * @param  array{outcome: string, customer_po?: ?string, customer_po_date?: ?string, lost_reason?: ?string, price_gap_pct?: mixed, note?: ?string}  $d
     * @return SalesOrder|null the order an accepted quotation made
     *
     * @throws \RuntimeException
     */
    public function close(Quotation $q, array $d): ?SalesOrder
    {
        if (! $this->canClose($q)) {
            throw new \RuntimeException('Only a sent quotation can be closed.');
        }
        $note = filled($d['note'] ?? null) ? trim($d['note']) : null;

        return DB::transaction(function () use ($q, $d, $note) {
            if ($d['outcome'] === 'ACCEPTED') {
                $order = $this->accept($q);
                $po = filled($d['customer_po'] ?? null) ? trim($d['customer_po']) : null;
                $q->update(['outcome' => 'ACCEPTED', 'customer_po' => $po, 'customer_po_date' => $d['customer_po_date'] ?? null, 'decision_note' => $note]);
                if ($po) {
                    $order->update(['customer_reference' => $po]);
                }
                QuotationEvent::log($q, 'NOTE', 'Customer order '.($po ?? 'reference not given').($d['customer_po_date'] ?? null ? ' dated '.\Illuminate\Support\Carbon::parse($d['customer_po_date'])->displayDate() : '').'.'.($note ? ' '.$note : ''));

                return $order;
            }
            if ($d['outcome'] === 'LOST') {
                $reason = $d['lost_reason'] ?? null;
                if (! array_key_exists((string) $reason, Quotation::LOST_REASONS)) {
                    throw new \RuntimeException('Choose why it was lost.');
                }
                $enquiry = $q->enquiry;
                if ($enquiry->isOpen()) {
                    app(EnquiryCloser::class)->close($enquiry, ['outcome' => 'LOST', 'close_reason' => self::ENQUIRY_REASONS[$reason], 'note' => $note,
                        'winning_price' => null]);
                }
                $q->update(['status' => 'REJECTED', 'outcome' => 'LOST', 'lost_reason' => $reason, 'decided_at' => now(), 'decision_note' => $note ?? Quotation::LOST_REASONS[$reason],
                    'price_gap_pct' => $reason === 'PRICE' && filled($d['price_gap_pct'] ?? null) ? (float) $d['price_gap_pct'] : null]);
                QuotationEvent::log($q, 'SYSTEM', 'Closed as lost: '.Quotation::LOST_REASONS[$reason].($reason === 'PRICE' && filled($d['price_gap_pct'] ?? null) ? ', about '.rtrim(rtrim(number_format((float) $d['price_gap_pct'], 1), '0'), '.').'% off' : '').'.'.($note ? ' '.$note : ''));

                return null;
            }
            $q->update(['status' => 'EXPIRED', 'outcome' => 'NO_REPLY', 'decided_at' => now(), 'decision_note' => $note ?? 'Ran out of validity with no reply']);
            QuotationEvent::log($q, 'SYSTEM', 'Closed with no reply. It ran out of validity and nobody came back.'.($note ? ' '.$note : ''));

            return null;
        });
    }

    /** A won enquiry gets the pattern developments its pattern decision needs, or a note on its log when the module is off. */
    private function openDevelopments(Enquiry $enquiry, bool $firstTime, Quotation $quote): void
    {
        if (! class_exists(DevelopmentOpener::class) || DevelopmentOpener::typeFor($enquiry, $firstTime) === null) {
            return;
        }
        if (! app(ModuleRegistry::class)->isEnabled('development')) {
            EnquiryEvent::system($enquiry, 'No pattern development opened: the Pattern developments module is off.');

            return;
        }
        app(DevelopmentOpener::class)->open($enquiry, $firstTime, true, $quote);
    }

    /** The current revision of a pattern that makes the item's product and has both weights recorded, newest first. */
    private function currentPatternRevisionFor(EnquiryItem $item): ?PatternRevision
    {
        if (! $item->product_id) {
            return null;
        }

        return PatternRevision::query()
            ->where('is_current', true)->whereNotNull('casting_weight_kg')->whereNotNull('pour_weight_kg')
            ->whereHas('cavityMap.productRevision', fn ($q) => $q->where('product_id', $item->product_id))
            ->orderByDesc('effective_from')->orderByDesc('created_at')
            ->first();
    }

    /** The revision an order will use: the approved one for repeat work, a new draft for new work or a customer change. */
    private function revisionFor(Product $product, bool $forceNew, float $weight): ProductRevision
    {
        $revs = $product->revisions()->get();
        if (! $forceNew) {
            $ok = $revs->whereIn('status', ['APPROVED', 'ACTIVE'])->sortByDesc('created_at')->first();
            if ($ok) {
                return $ok;
            }
            $draft = $revs->where('status', 'DRAFT')->sortByDesc('created_at')->first();
            if ($draft) {
                return $draft;
            }
        }
        $names = $revs->pluck('revision');
        $next = \App\Models\ProductRevision::nextLabel($names);

        return $product->revisions()->create(['revision' => $next, 'status' => 'DRAFT', 'net_weight_kg' => $weight > 0 ? $weight : null, 'engineering_notes' => 'Created from an accepted quotation. Approve after samples and specification are in place.']);
    }
}
