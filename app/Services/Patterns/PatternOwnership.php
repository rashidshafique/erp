<?php

namespace App\Services\Patterns;

use App\Models\Pattern;
use App\Models\PatternOwnershipEvent;
use App\Models\PatternRevision;
use App\Models\PatternRevisionCavity;
use App\Models\ProductionOrder;
use App\Services\Approvals\ApprovalGate;
use App\Support\PatternOwnershipRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Who owns a pattern and where it is, and what that allows: a customer's pattern makes that customer's castings only
 * (by the rule), a returned or scrapped one makes none, scrapping a customer's pattern needs an approval, and a pattern
 * being paid off passes to the customer once its quantity has been dispatched.
 */
class PatternOwnership
{
    private const FIELDS = ['owner_type', 'owner_customer_id', 'supplied_by_customer', 'ownership_quotation_id', 'ownership_reference', 'acquired_on', 'insured_value', 'amortise_qty', 'ownership_notes', 'custody', 'custody_location'];

    /**
     * Saves ownership and custody fields, recording an event for each change of owner or custody.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Pattern $pattern, array $data, ?string $reference = null, ?string $notes = null): void
    {
        $data = array_intersect_key($data, array_flip(self::FIELDS));
        if (($data['owner_type'] ?? $pattern->owner_type) === 'FOUNDRY') {
            $data['owner_customer_id'] = null;
        }
        $oldOwner = $pattern->ownerLabel();
        $oldCustody = $this->custodyLabel($pattern);
        $pattern->fill($data);
        if (! $pattern->isDirty()) {
            return;
        }
        $ownerChanged = $pattern->isDirty(['owner_type', 'owner_customer_id']);
        $custodyChanged = $pattern->isDirty(['custody', 'custody_location']);
        $pattern->save();
        $pattern->unsetRelation('ownerCustomer');
        if ($ownerChanged) {
            $this->event($pattern, 'OWNER_CHANGED', $oldOwner, $pattern->ownerLabel(), $reference ?? $pattern->ownership_reference, $notes);
        }
        if ($custodyChanged) {
            $this->event($pattern, 'CUSTODY_CHANGED', $oldCustody, $this->custodyLabel($pattern), $reference, $notes);
        }
    }

    public function custodyLabel(Pattern $pattern): string
    {
        return (Pattern::CUSTODIES[$pattern->custody] ?? $pattern->custody).($pattern->custody_location ? ' · '.$pattern->custody_location : '');
    }

    private function event(Pattern $pattern, string $type, ?string $from, ?string $to, ?string $reference, ?string $notes): PatternOwnershipEvent
    {
        return $pattern->ownershipEvents()->create([
            'event_type' => $type, 'from_value' => $from, 'to_value' => $to, 'reference' => $reference, 'notes' => $notes,
            'occurred_on' => today(), 'recorded_by' => auth()->user()?->name,
        ]);
    }

    /** The customer an order is for; a stock order has none. */
    public function orderCustomerId(?ProductionOrder $order): ?string
    {
        return $order?->salesOrderLine?->salesOrder?->customer_id;
    }

    /**
     * Whether a pattern revision may mould for an order. A returned or scrapped pattern never may. A customer's pattern on
     * another customer's or a stock order follows the rule: blocked (an exception), or allowed with the returned warning.
     */
    public function checkUse(PatternRevision $revision, ?ProductionOrder $order): ?string
    {
        $pattern = $revision->pattern;
        if (! $pattern) {
            return null;
        }
        if (in_array($pattern->custody, ['RETURNED', 'SCRAPPED'], true)) {
            throw ValidationException::withMessages(['pattern_revision_id' => "Pattern {$pattern->code} is ".strtolower(Pattern::CUSTODIES[$pattern->custody]).' and cannot be moulded.']);
        }
        if (! $pattern->isCustomers() || ! $order) {
            return null;
        }
        $customerId = $this->orderCustomerId($order);
        if ($customerId === $pattern->owner_customer_id) {
            return null;
        }
        $rule = PatternOwnershipRules::get('other_customer');
        if ($rule === 'ALLOW') {
            return null;
        }
        $message = "Pattern {$pattern->code} belongs to {$pattern->ownerLabel()}; order {$order->production_order_number} is ".($customerId ? 'for another customer' : 'a stock order').'.';
        if ($rule === 'BLOCK') {
            throw ValidationException::withMessages(['pattern_revision_id' => $message.' Their pattern may only make their castings (pattern ownership rules).']);
        }

        return $message;
    }

    /**
     * Whose tooling makes a product, for quoting a customer: their own pattern carries no tooling charge, one being paid
     * off is charged as agreed, and another customer's pattern is flagged.
     *
     * @return array{customer_owned: bool, note: ?string, warning: bool}
     */
    public function toolingFor(?string $customerId, ?string $productId): array
    {
        $none = ['customer_owned' => false, 'note' => null, 'warning' => false];
        if (! $productId) {
            return $none;
        }
        $revisionIds = PatternRevisionCavity::join('product_revisions', 'product_revisions.id', '=', 'pattern_revision_cavities.product_revision_id')
            ->where('product_revisions.product_id', $productId)->select('pattern_revision_cavities.pattern_revision_id');
        $patterns = Pattern::with('ownerCustomer')->whereIn('id', PatternRevision::whereIn('id', $revisionIds)->select('pattern_id'))
            ->whereNotIn('custody', ['RETURNED', 'SCRAPPED'])->orderBy('code')->get();
        if ($patterns->isEmpty()) {
            return $none;
        }
        if ($own = $patterns->first(fn ($p) => $p->owner_type === 'CUSTOMER' && $p->owner_customer_id === $customerId)) {
            return ['customer_owned' => true, 'note' => "Tooling: the customer's own pattern {$own->code}, so no tooling charge.", 'warning' => false];
        }
        if ($paying = $patterns->first(fn ($p) => $p->owner_type === 'PAYING_OFF' && $p->owner_customer_id === $customerId)) {
            return ['customer_owned' => false, 'note' => "Tooling: pattern {$paying->code} is being paid off by this customer.", 'warning' => false];
        }
        if ($patterns->every(fn ($p) => $p->isCustomers())) {
            $other = $patterns->first();

            return ['customer_owned' => false, 'warning' => true,
                'note' => "Pattern {$other->code} belongs to {$other->ownerLabel()}".(PatternOwnershipRules::get('other_customer') === 'BLOCK' ? ' and may not make castings for this customer' : '').'; new tooling may be needed.'];
        }

        return $none;
    }

    /** Products the pattern makes, on any of its revisions. */
    private function productIds(Pattern $pattern): Collection
    {
        return PatternRevisionCavity::whereIn('pattern_revision_id', $pattern->revisions()->pluck('id'))
            ->join('product_revisions', 'product_revisions.id', '=', 'pattern_revision_cavities.product_revision_id')
            ->distinct()->pluck('product_revisions.product_id');
    }

    /** @return array{dispatched: int, target: ?int, ready: bool} castings of its products dispatched to the paying customer */
    public function payOffProgress(Pattern $pattern): array
    {
        if ($pattern->owner_type !== 'PAYING_OFF' || ! $pattern->owner_customer_id) {
            return ['dispatched' => 0, 'target' => null, 'ready' => false];
        }
        $dispatched = (int) DB::table('dispatch_items')
            ->join('dispatches', 'dispatches.id', '=', 'dispatch_items.dispatch_id')
            ->join('sales_order_lines', 'sales_order_lines.id', '=', 'dispatch_items.sales_order_line_id')
            ->where('dispatches.customer_id', $pattern->owner_customer_id)
            ->whereIn('dispatches.status', ['DISPATCHED', 'DELIVERED'])
            ->whereIn('sales_order_lines.product_id', $this->productIds($pattern))
            ->when($pattern->acquired_on, fn ($q) => $q->where('dispatches.dispatched_at', '>=', $pattern->acquired_on))
            ->sum('dispatch_items.quantity_dispatched');
        $target = $pattern->amortise_qty;

        return ['dispatched' => $dispatched, 'target' => $target, 'ready' => $target !== null && $dispatched >= $target];
    }

    /** After a dispatch: patterns of that customer that are now paid off pass to them under the "automatic" rule. */
    public function dispatchedTo(string $customerId): int
    {
        if (PatternOwnershipRules::get('pay_off') !== 'AUTO') {
            return 0;
        }
        $passed = 0;
        foreach (Pattern::where('owner_type', 'PAYING_OFF')->where('owner_customer_id', $customerId)->whereNotNull('amortise_qty')->get() as $pattern) {
            $progress = $this->payOffProgress($pattern);
            if ($progress['ready']) {
                $this->payOff($pattern, null, "Paid off automatically: {$progress['dispatched']} of {$progress['target']} castings dispatched.");
                $passed++;
            }
        }

        return $passed;
    }

    /** Ownership passes to the customer who paid the pattern off. */
    public function payOff(Pattern $pattern, ?string $reference, ?string $notes): void
    {
        if ($pattern->owner_type !== 'PAYING_OFF') {
            throw ValidationException::withMessages(['pay_off' => "Pattern {$pattern->code} is not being paid off."]);
        }
        $from = $pattern->ownerLabel();
        $pattern->update(['owner_type' => 'CUSTOMER']);
        $this->event($pattern, 'PAID_OFF', $from, $pattern->ownerLabel(), $reference, $notes);
    }

    /** Hands the pattern back to its customer: it can no longer be moulded and leaves the pattern list. */
    public function returnToCustomer(Pattern $pattern, string $reference, ?string $notes): void
    {
        if (! $pattern->isCustomers()) {
            throw ValidationException::withMessages(['reference' => "Pattern {$pattern->code} is the foundry's; there is no customer to return it to."]);
        }
        $from = $this->custodyLabel($pattern);
        $pattern->update(['custody' => 'RETURNED', 'custody_location' => $pattern->ownerCustomer?->name]);
        $this->event($pattern, 'RETURNED', $from, $this->custodyLabel($pattern), $reference, $notes);
        $pattern->delete();
    }

    /**
     * Scraps the pattern. A customer's pattern needs their written consent (the reference) and an approval: the first call
     * raises the request and returns its message; once approved, the next call scraps it. Returns null when it is scrapped.
     */
    public function scrap(Pattern $pattern, string $reference, ?string $notes): ?string
    {
        if ($pattern->isCustomers()) {
            $message = null;
            $label = "{$pattern->code} ({$pattern->ownerLabel()}), consent {$reference}";
            if (! app(ApprovalGate::class)->passes('pattern.scrap_customer_pattern', 'Pattern', (string) $pattern->id, $label, $message, $notes)) {
                return $message;
            }
        }
        $from = $this->custodyLabel($pattern);
        $pattern->update(['custody' => 'SCRAPPED', 'custody_location' => null]);
        $this->event($pattern, 'SCRAPPED', $from, 'Scrapped', $reference, $notes);
        $pattern->delete();

        return null;
    }

    /**
     * Patterns held but unused for the idle period, oldest first, with what storing them costs the customer when storage is
     * charged.
     *
     * @return Collection<int, array{pattern: Pattern, last_used: ?Carbon, months: int, charge: ?float}>
     */
    public function idle(?string $customerId = null): Collection
    {
        $months = (int) PatternOwnershipRules::get('idle_months');
        $charging = PatternOwnershipRules::get('storage') === 'CHARGE';
        $free = (int) PatternOwnershipRules::get('storage_free_months');
        $rate = (float) PatternOwnershipRules::get('storage_rate');
        $lastRuns = DB::table('moulding_runs')->join('pattern_revisions', 'pattern_revisions.id', '=', 'moulding_runs.pattern_revision_id')
            ->groupBy('pattern_revisions.pattern_id')->selectRaw('pattern_revisions.pattern_id, max(moulding_runs.run_date) as last_run')->pluck('last_run', 'pattern_id');

        return Pattern::with('ownerCustomer')->whereIn('custody', ['IN_STORE', 'PATTERN_SHOP', 'LENT'])
            ->when($customerId, fn ($q) => $q->where('owner_customer_id', $customerId))
            ->get()
            ->map(function (Pattern $pattern) use ($lastRuns, $charging, $free, $rate) {
                $since = isset($lastRuns[$pattern->id]) ? Carbon::parse($lastRuns[$pattern->id]) : ($pattern->acquired_on ?? $pattern->created_at);
                $idle = (int) floor($since->diffInMonths(now(), true));
                $chargeable = $pattern->isCustomers() && $charging ? max(0, $idle - $free) : 0;

                return ['pattern' => $pattern, 'last_used' => isset($lastRuns[$pattern->id]) ? $since : null, 'months' => $idle, 'charge' => $charging && $pattern->isCustomers() ? round($chargeable * $rate, 2) : null];
            })
            ->filter(fn ($row) => $row['months'] >= $months)
            ->sortByDesc('months')->values();
    }
}
