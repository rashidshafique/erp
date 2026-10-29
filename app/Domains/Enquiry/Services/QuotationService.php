<?php

namespace App\Domains\Enquiry\Services;

use App\Domains\Core\ModuleRegistry;
use App\Domains\Development\Services\DevelopmentOpener;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryEvent;
use App\Domains\Enquiry\Models\EnquiryItem;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Sales\Models\SalesOrder;
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

    /** A new draft version for the enquiry, priced from its items. Earlier drafts and issued versions are replaced. */
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
                'enquiry_id' => $enquiry->id, 'version' => ($prev?->version ?? 0) + 1, 'status' => 'DRAFT', 'currency' => config('quotation.currency'),
                'payment_terms' => $prev?->payment_terms ?? $enquiry->customer?->payment_terms, 'delivery_weeks' => $prev?->delivery_weeks,
                'tooling_mode' => $prev?->tooling_mode ?? 'NONE', 'tooling_amortise_qty' => $prev?->tooling_amortise_qty, 'notes' => $prev?->notes,
                'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name,
            ]);
            foreach ($enquiry->items as $i => $item) {
                $in = $inputs[$item->id];
                $tooling = app(PatternOwnership::class)->toolingFor($enquiry->customer_id, $item->product_id);
                $old = $prev?->lines->firstWhere('enquiry_item_id', $item->id);
                $line = $q->lines()->create([
                    'enquiry_item_id' => $item->id, 'line_no' => $i + 1, 'qty' => $in['qty'], 'weight_kg' => $in['weight_kg'], 'poured_kg' => $in['poured_kg'], 'family' => $in['family'],
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

        return $out;
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
            $ok = app(ApprovalGate::class)->passes('quotation.issue_low_margin', 'Quotation', $q->id, $q->code.sprintf(' (margin %.1f%% against minimum %.1f%%)', $low, $min), $msg);
            if (! $ok) {
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
        });

        return null;
    }

    public function reject(Quotation $q, ?string $reason): void
    {
        abort_unless(in_array($q->status, ['ISSUED', 'EXPIRED'], true), 422);
        $q->update(['status' => 'REJECTED', 'decided_at' => now(), 'decision_note' => $reason]);
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
            $this->openDevelopments($enquiry, $firstTime, $q);

            return $order;
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
