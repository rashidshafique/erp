<?php

namespace App\Services\Orders;

use App\Models\ContractReview;
use App\Models\CustomerProductRequirement;
use App\Models\IronGrade;
use App\Models\PatternRevisionCavity;
use App\Models\ProductSpecification;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\ThermalWindow;
use App\Services\FurnaceEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractReviewer
{
    private const OK_STATUS = ['APPROVED', 'EFFECTIVE', 'ACTIVE'];

    public function __construct(private FurnaceEligibility $furnaces)
    {
    }

    /** Run all checks and store a new review. Earlier unfinished reviews are superseded. */
    public function evaluate(SalesOrder $order): ContractReview
    {
        return DB::transaction(function () use ($order) {
            $order->contractReviews()->whereIn('status', ['DRAFT', 'IN_REVIEW', 'REJECTED'])->update(['status' => 'SUPERSEDED']);
            $number = (int) $order->contractReviews()->max('review_number') + 1;

            $review = $order->contractReviews()->create(['review_number' => $number, 'status' => 'IN_REVIEW', 'verdict' => 'PASS', 'evaluated_at' => now()]);
            $order->load(['lines.product', 'lines.productRevision', 'customer']);

            $worst = 'PASS';
            $rank = ['PASS' => 0, 'WARN' => 1, 'BLOCK' => 2];

            if ($order->lines->isEmpty()) {
                $this->add($review, null, 'LINES', 'BLOCK', 'The order has no lines.');
                $worst = 'BLOCK';
            }
            foreach ($order->lines as $line) {
                foreach ($this->checkLine($order, $line) as [$code, $sev, $msg]) {
                    $this->add($review, $line, $code, $sev, $msg);
                    if ($rank[$sev] > $rank[$worst]) {
                        $worst = $sev;
                    }
                }
            }

            $review->update(['verdict' => $worst, 'status' => $worst === 'BLOCK' ? 'REJECTED' : 'IN_REVIEW']);
            $order->update(['status' => 'UNDER_REVIEW']);

            return $review->fresh('items');
        });
    }

    /** @return array<int, array{0: string, 1: string, 2: string}> */
    public function checkLine(SalesOrder $order, SalesOrderLine $line): array
    {
        $out = [];
        $rev = $line->productRevision;
        $label = trim(($line->product?->name ?? '').' '.($line->product?->code ?? '?').'-Rev'.($rev?->revision ?? '?'));

        if ((float) $line->ordered_qty <= 0) {
            $out[] = ['QUANTITY', 'BLOCK', "{$label}: the quantity must be above zero."];
        }

        // Product revision released for production
        if (! $rev || ! in_array($rev->status, ['APPROVED', 'ACTIVE'], true)) {
            $out[] = ['REVISION_STATUS', 'BLOCK', "{$label} is not an approved revision (status ".($rev?->status ?? 'missing').').'];
        } else {
            $out[] = ['REVISION_STATUS', 'PASS', "{$label} is an approved revision."];
        }

        // Product specification
        $now = now();
        $specs = ProductSpecification::where('product_revision_id', $line->product_revision_id)
            ->whereIn('status', self::OK_STATUS)->whereNull('superseded_at')
            ->where(fn ($q) => $q->whereNull('effective_at')->orWhere('effective_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))->get();
        if ($specs->isEmpty()) {
            $out[] = ['PRODUCT_SPEC', 'BLOCK', "{$label} has no approved, effective product specification."];
        } else {
            $out[] = ['PRODUCT_SPEC', 'PASS', "Specification {$specs->first()->specification_code} is approved."];
        }

        // Customer requirement
        $reqs = CustomerProductRequirement::where('customer_id', $order->customer_id)->where('product_revision_id', $line->product_revision_id)
            ->whereNull('superseded_at')->get();
        $okReq = $reqs->first(fn ($r) => in_array($r->status, self::OK_STATUS, true)
            && ($r->effective_at === null || $r->effective_at <= $now) && ($r->expires_at === null || $r->expires_at > $now));
        if ($reqs->isEmpty()) {
            $out[] = ['CUSTOMER_REQUIREMENT', 'WARN', "No customer requirement is on file for {$label}. Confirm there are no extra customer demands."];
        } elseif (! $okReq) {
            $out[] = ['CUSTOMER_REQUIREMENT', 'WARN', "The customer requirement for {$label} is not approved or not in effect yet."];
        } else {
            $out[] = ['CUSTOMER_REQUIREMENT', 'PASS', "Customer requirement {$okReq->requirement_code} is approved."];
        }

        // Grade: customer demand versus our specification
        $specGrade = $specs->pluck('material_grade')->filter()->first();
        $custGrade = $okReq?->customer_grade ?? $reqs->pluck('customer_grade')->filter()->first();
        $grade = $this->resolveGrade($out, $label, $specGrade, $custGrade);

        if ($grade) {
            $eligible = $this->furnaces->eligible($grade);
            $out[] = $eligible->isEmpty()
                ? ['FURNACE', 'BLOCK', "No active furnace can melt {$grade->code}."]
                : ['FURNACE', 'PASS', 'Can be melted in: '.$eligible->pluck('code')->implode(', ').'.'];

            $w = ThermalWindow::where('iron_grade_id', $grade->id)->first();
            $out[] = ($w && ! $w->isEmpty())
                ? ['THERMAL_WINDOW', 'PASS', "A thermal acceptance window is set for {$grade->code}."]
                : ['THERMAL_WINDOW', 'WARN', "No thermal acceptance window is set for {$grade->code}, so thermal analysis cannot verify this order."];
        }

        // Pattern with cavity map
        $patterns = PatternRevisionCavity::with('patternRevision.pattern')
            ->whereHas('productRevision', fn ($q) => $q->where('product_id', $line->product_id))
            ->whereHas('patternRevision', fn ($q) => $q->whereIn('status', ['APPROVED']))->get();
        $names = $patterns->map(fn ($c) => $c->patternRevision?->pattern ? $c->patternRevision->pattern->code.' '.$c->patternRevision->pattern->name.' '.$c->patternRevision->shortCode() : null)->filter()->unique()->implode(', ');
        $out[] = $patterns->isNotEmpty()
            ? ['PATTERN', 'PASS', "{$patterns->count()} cavity(ies) on approved pattern revisions make this product: {$names}."]
            : ['PATTERN', 'WARN', "No cavity of an approved pattern revision is linked to {$line->product?->code}. Link cavities on the pattern page."];

        // Control plan
        $plans = DB::table('process_control_plans')->where('product_revision_id', $line->product_revision_id)->whereIn('status', self::OK_STATUS)->count();
        $out[] = $plans > 0
            ? ['CONTROL_PLAN', 'PASS', 'An approved process control plan exists.']
            : ['CONTROL_PLAN', 'WARN', "No approved process control plan exists for {$label}."];

        // Delivery date
        $due = $line->requested_delivery_date ?? $order->requested_delivery_date;
        if ($due) {
            $min = now()->addDays((int) config('foundry.planner.lead_time_days'))->startOfDay();
            if ($due->lt(now()->startOfDay())) {
                $out[] = ['DELIVERY_DATE', 'BLOCK', 'The requested delivery date is in the past.'];
            } elseif ($due->lt($min)) {
                $out[] = ['DELIVERY_DATE', 'WARN', 'The requested delivery is sooner than the standard lead time of '.config('foundry.planner.lead_time_days').' days.'];
            } else {
                $out[] = ['DELIVERY_DATE', 'PASS', 'The delivery date allows the standard lead time.'];
            }
        } else {
            $out[] = ['DELIVERY_DATE', 'WARN', 'No delivery date is set.'];
        }

        return $out;
    }

    /** Decide the iron grade and record conflicts between our specification and the customer's. */
    private function resolveGrade(array &$out, string $label, ?string $specGrade, ?string $custGrade): ?IronGrade
    {
        $s = IronGrade::resolve($specGrade);
        $c = IronGrade::resolve($custGrade);

        if (blank($specGrade) && blank($custGrade)) {
            $out[] = ['GRADE', 'BLOCK', "No material grade is named in the specification or the customer requirement for {$label}."];

            return null;
        }
        if (filled($specGrade) && ! $s) {
            $out[] = ['GRADE', 'WARN', "The specification grade \"{$specGrade}\" is not in the iron grade list; add it or correct it."];
        }
        if (filled($custGrade) && ! $c) {
            $out[] = ['GRADE', 'WARN', "The customer grade \"{$custGrade}\" is not in the iron grade list; the standards equivalent cannot be checked."];
        }
        if ($s && $c && $s['grade']->id !== $c['grade']->id) {
            $out[] = ['GRADE', 'BLOCK', "Conflict: our specification says {$s['grade']->code}, but the customer's grade \"{$custGrade}\" is {$c['grade']->code}."];

            return $s['grade'];
        }
        if ($c && ! $c['exact'] && $s) {
            $out[] = ['GRADE', 'WARN', "Customer grade \"{$custGrade}\" is treated as the nearest equivalent of {$c['grade']->code}. Confirm the equivalence with the customer."];
        } elseif ($s || $c) {
            $g = ($s ?? $c)['grade'];
            $out[] = ['GRADE', 'PASS', "Iron grade {$g->code}".($s && $c ? ' matches the customer demand.' : '.')];
        }

        return ($s ?? $c)['grade'] ?? null;
    }

    private function add(ContractReview $review, ?SalesOrderLine $line, string $code, string $sev, string $msg): void
    {
        $review->items()->create(['sales_order_line_id' => $line?->id, 'check_code' => $code, 'severity' => $sev, 'message' => $msg]);
    }

    public function acknowledge(ContractReview $review, string $itemId, string $reason): void
    {
        $item = $review->items()->findOrFail($itemId);
        if ($item->severity !== 'WARN') {
            throw ValidationException::withMessages(['item' => 'Only warnings can be acknowledged.']);
        }
        if (strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'Give a reason of at least 5 characters.']);
        }
        $item->update(['acknowledgement' => trim($reason)]);
    }

    /** Sales or quality signs. Both signatures approve the review. */
    public function sign(ContractReview $review, string $role, string $name): ContractReview
    {
        if (! in_array($review->status, ['IN_REVIEW'], true)) {
            throw ValidationException::withMessages(['review' => 'Only a review in progress can be signed. Run a new evaluation first.']);
        }
        if ($review->verdict === 'BLOCK') {
            throw ValidationException::withMessages(['review' => 'This review has blocking findings and cannot be signed.']);
        }
        if (strlen(trim($name)) < 2) {
            throw ValidationException::withMessages(['name' => 'Enter the name of the person signing.']);
        }
        $open = $review->items()->where('severity', 'WARN')->whereNull('acknowledgement')->count();
        if ($open > 0) {
            throw ValidationException::withMessages(['review' => "{$open} warning(s) must be acknowledged with a reason before signing."]);
        }

        $col = $role === 'quality' ? 'quality' : 'sales';
        $review->update(["{$col}_signed_by" => trim($name), "{$col}_signed_at" => now()]);
        $review->refresh();

        if ($review->sales_signed_at && $review->quality_signed_at) {
            $review->update(['status' => 'APPROVED']);
            $review->salesOrder->update(['status' => 'CONFIRMED']);
        }

        return $review;
    }
}
