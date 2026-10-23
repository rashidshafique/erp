<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PatternRevisionCavity;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\SalesOrderLine;
use App\Services\Planning\HeatPlanner;
use App\Support\MeltingCalendar;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Help pages that live inside the app. */
class HelpController extends Controller
{
    /** The new customer checklist; with a customer chosen, the steps are ticked from what the system holds for them. */
    public function newCustomer(Request $request, HeatPlanner $planner): View
    {
        $customer = $request->filled('customer') ? Customer::find($request->query('customer')) : null;

        return view('help.new-customer', [
            'customers' => Customer::active()->orderedByName()->get(),
            'customer' => $customer,
            'rows' => $customer ? $this->progress($customer, $planner) : collect(),
            'nextMeltingDay' => MeltingCalendar::next(),
            'meltingRule' => MeltingCalendar::describe(),
            'meltingSet' => MeltingCalendar::enabled(),
        ]);
    }

    /**
     * One row per product the customer has ordered: what is in place and what is missing before it can be moulded and poured.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function progress(Customer $customer, HeatPlanner $planner)
    {
        $productIds = SalesOrderLine::whereHas('salesOrder', fn ($q) => $q->where('customer_id', $customer->id))->pluck('product_id')->unique();

        return Product::with('revisions')->whereIn('id', $productIds)->orderBy('code')->get()->map(function (Product $product) use ($planner, $customer) {
            $revisions = $product->revisions->where('status', '!=', 'OBSOLETE')->sortByDesc('created_at');
            $revision = $revisions->firstWhere('status', 'APPROVED') ?? $revisions->first();
            $cavities = $product->revisions->isEmpty() ? collect() : PatternRevisionCavity::with('patternRevision.pattern')
                ->whereIn('product_revision_id', $product->revisions->pluck('id'))->get()->filter(fn ($c) => $c->patternRevision?->pattern && ! $c->patternRevision->pattern->trashed());
            $patterns = $cavities->map(fn ($c) => $c->patternRevision->pattern)->unique('id');

            return [
                'product' => $product,
                'revision' => (bool) $revision,
                'grade' => $revision ? $planner->gradeFor($revision)?->code : null,
                'weight' => $revision ? ($planner->metalPerProduct($revision)['kg'] ?? null) : null,
                'patterns' => $patterns,
                'mapped' => $patterns->isNotEmpty(),
                'owned' => $patterns->isNotEmpty() && $patterns->every(fn ($p) => $p->owner_customer_id === $customer->id && $p->owner_type !== 'FOUNDRY'),
                'order' => ProductionOrder::where('product_id', $product->id)->whereIn('status', ['RELEASED', 'IN_PROGRESS'])->exists(),
            ];
        });
    }
}
