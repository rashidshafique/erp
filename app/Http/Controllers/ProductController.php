<?php

namespace App\Http\Controllers;

use App\Domains\Core\ModuleRegistry;
use App\Models\CoreRequirement;
use App\Models\CustomerProductRequirement;
use App\Models\Pattern;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\ProductionOrder;
use App\Services\PatternCavityMap;
use App\Services\Planning\HeatPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /** The list itself is the product-list Livewire component; this is the page around it. */
    public function index()
    {
        return view('products.index');
    }

    public function create()
    {
        return view('products.create', [
            'product' => new Product,
            'revisionStatuses' => ['DRAFT', 'APPROVED', 'OBSOLETE'],
        ]);
    }

    public function store(Request $request)
    {
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);   // "flg-1" and "FLG-1" are the same code
        }
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'unique:products,code'],   // blank = generated
            'name' => ['required', 'string', 'max:255'],
            'product_type' => ['required', Rule::in(['CASTING', 'CORE', 'OTHER'])],
            'base_uom' => ['required', 'string', 'max:20'],
            'nominal_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],

            'revision' => ['required', 'string', 'max:20'],
            'revision_status' => ['required', Rule::in(['DRAFT', 'APPROVED', 'OBSOLETE'])],
            'net_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'engineering_notes' => ['nullable', 'string'],
            ...Product::tradeRules(),
        ]);

        $product = DB::transaction(function () use ($data) {
            $product = Product::create([
                'code' => filled($data['code'] ?? null) ? strtoupper(trim($data['code'])) : null,
                'name' => trim($data['name']),
                'product_type' => $data['product_type'],
                'base_uom' => $data['base_uom'],
                'nominal_weight_kg' => $data['nominal_weight_kg'] ?? null,
                'description' => $data['description'] ?? null,
                'active' => $data['active'],
                ...Arr::only($data, ['inventory_class', 'supply_type', 'tariff_code', 'tax_uom', 'origin_country']),
            ]);

            $product->revisions()->create([
                'revision' => trim($data['revision']),
                'status' => $data['revision_status'],
                'net_weight_kg' => $data['net_weight_kg'] ?? null,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
                'engineering_notes' => $data['engineering_notes'] ?? null,
            ]);

            return $product;
        });

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Product and initial revision created successfully.');
    }

    /** Names the iron grade on an approved specification for a revision, which is what the contract review and the melting plan read. */
    public function setGrade(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'product_revision_id' => ['required', 'uuid', Rule::exists('product_revisions', 'id')->where('product_id', $product->id)],
            'iron_grade_id' => ['required', 'uuid', 'exists:iron_grades,id'],
        ]);
        $revision = \App\Models\ProductRevision::findOrFail($data['product_revision_id']);
        $grade = \App\Models\IronGrade::findOrFail($data['iron_grade_id']);
        \App\Models\ProductSpecification::updateOrCreate(
            ['product_revision_id' => $revision->id, 'specification_code' => 'SPEC-'.$product->code.'-'.$revision->revision],
            ['title' => 'Iron grade', 'status' => 'APPROVED', 'material_grade' => $grade->code, 'approved_at' => now(), 'effective_at' => now(), 'superseded_at' => null],
        );

        return back()->with('success', "{$grade->code} set on {$product->code} rev {$revision->revision}.");
    }

    /**
     * Links the product to a pattern from the product page: puts the product on a cavity of the chosen pattern revision (the
     * current one unless a revision is picked). A pattern or product with no revision gets its first one here. Without a cavity number it
     * takes the revision's first free cavity.
     */
    public function linkPattern(Request $request, Product $product, PatternCavityMap $map): RedirectResponse
    {
        $data = $request->validate([
            'pattern_id' => ['required', 'uuid', Rule::exists('patterns', 'id')->whereNull('deleted_at')],
            'pattern_revision_id' => ['nullable', 'uuid'],
            'product_revision_id' => ['nullable', 'uuid', Rule::exists('product_revisions', 'id')->where('product_id', $product->id)],
            'cavity_number' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ], ['pattern_id.required' => 'Choose a pattern.', 'product_revision_id.exists' => 'Choose a revision of this product.']);

        [$pattern, $revision, $number] = DB::transaction(function () use ($data, $product, $map) {
            $pattern = Pattern::findOrFail($data['pattern_id']);
            if (filled($data['pattern_revision_id'] ?? null)) {
                $revision = $pattern->revisions()->find($data['pattern_revision_id']);
                if (! $revision) {
                    throw ValidationException::withMessages(['pattern_revision_id' => 'Choose a revision of this pattern.']);
                }
            } else {
                $revision = $pattern->currentRevision()->first()
                    ?? $pattern->addRevision(['change_description' => 'First revision, recorded when linking '.$product->code]);
            }

            $productRevisionId = $data['product_revision_id'] ?? $product->linkableRevision()->id;

            $taken = $revision->cavityMap()->pluck('cavity_number')->all();
            $number = $data['cavity_number'] ?? null;
            if ($number === null) {
                $limit = $revision->cavities_per_mould ?? (count($taken) ? max($taken) + 1 : 1);
                $number = collect(range(1, max(1, (int) $limit)))->first(fn ($n) => ! in_array($n, $taken, true));
                if ($number === null) {
                    throw ValidationException::withMessages(['cavity_number' => "Every cavity of {$revision->label()} already has a product. Raise the cavity count on the pattern page, or pick another revision."]);
                }
            }

            $cavity = ['cavity_number' => (int) $number, 'product_revision_id' => $productRevisionId, 'cavity_role' => 'WHOLE', 'pieces_per_product' => 1];
            $map->assertDefinable($revision, $cavity);
            $revision->cavityMap()->create($cavity);

            return [$pattern, $revision, $number];
        });

        $response = back()->with('success', "{$product->code} linked to {$pattern->name} ({$revision->label()}), cavity {$number}.");

        return $revision->isSuperseded() ? $response->with('warning', PatternCavityController::supersededWarning($revision)) : $response;
    }

    public function show(Product $product)
    {
        $product->load([
            'revisions' => fn ($query) => $query
                ->orderByDesc('created_at')
                ->orderBy('revision'),
            'revisions.patternCavities.patternRevision.pattern',
        ]);

        return view('products.show', ['product' => $product, 'facts' => $this->facts($product)]);
    }

    /**
     * What the overview and BOM tabs show about a product's current revision: customer, grade, weights, tooling, price,
     * a year of orders and dispatches, and the cost of the bill of materials per casting.
     *
     * @return array<string, mixed>
     */
    private function facts(Product $product): array
    {
        $revision = $product->currentRevision()?->load('bomLines.material', 'patternCavities.patternRevision.pattern.preferredStation');
        $cavity = $revision?->patternCavities->filter(fn ($c) => $c->patternRevision?->pattern)->sortByDesc(fn ($c) => $c->patternRevision->is_current)->first();
        $patternRevision = $cavity?->patternRevision;
        $castingKg = $revision?->net_weight_kg ?? $product->nominal_weight_kg;
        $pouredKg = $cavity?->poured_weight_kg
            ?: ($patternRevision?->pour_weight_kg && $patternRevision->cavities_per_mould ? (float) $patternRevision->pour_weight_kg / $patternRevision->cavities_per_mould : null);
        $perMould = $patternRevision?->cavities_per_mould ?: null;

        $order = fn () => DB::table('sales_order_lines as sol')->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')->where('sol.product_id', $product->id);
        $latest = $order()->orderByDesc('so.order_date')->orderByDesc('sol.created_at')->first(['sol.unit_price', 'so.order_number', 'so.customer_id']);
        $customer = $latest ? DB::table('customers as c')->join('parties as pa', 'pa.id', '=', 'c.party_id')->where('c.id', $latest->customer_id)->value('pa.name') : null;
        $open = $order()->whereIn('so.status', ['CONFIRMED', 'IN_PRODUCTION'])->orderByDesc('so.order_date')->first(['so.order_number', 'sol.ordered_qty']);
        $requirement = $revision ? CustomerProductRequirement::where('product_revision_id', $revision->id)->whereNull('superseded_at')->orderByDesc('created_at')->first() : null;
        $dispatched = app(ModuleRegistry::class)->isEnabled('dispatch')
            ? (float) DB::table('dispatch_items as di')->join('dispatches as d', 'd.id', '=', 'di.dispatch_id')->join('packing_record_items as pri', 'pri.id', '=', 'di.packing_record_item_id')
                ->join('casting_lots as cl', 'cl.id', '=', 'pri.casting_lot_id')->where('cl.product_id', $product->id)->where('d.dispatched_at', '>=', now()->subYear())->sum('di.quantity_dispatched')
            : null;

        $lines = ($revision?->bomLines ?? collect())->map(function ($line) use ($pouredKg, $perMould) {
            $perCasting = match ($line->basis) {
                'PER_MOULD' => $perMould ? (float) $line->quantity / $perMould : null,
                'PER_KG_POURED' => $pouredKg ? (float) $line->quantity * (float) $pouredKg : null,
                default => (float) $line->quantity,
            };
            $unitCost = $line->material?->standardCost();

            return ['line' => $line, 'perCasting' => $perCasting, 'cost' => $perCasting !== null && $unitCost !== null ? $perCasting * $unitCost : null];
        });

        return [
            'revision' => $revision,
            'grade' => $revision ? app(HeatPlanner::class)->gradeFor($revision) : null,
            'cavity' => $cavity,
            'patternRevision' => $patternRevision,
            'castingKg' => $castingKg !== null ? (float) $castingKg : null,
            'pouredKg' => $pouredKg ? (float) $pouredKg : null,
            'perMould' => $perMould,
            'process' => $patternRevision?->pattern->madeForSand(),
            'cores' => $revision ? CoreRequirement::where('product_revision_id', $revision->id)->with('coreDefinition')->get() : collect(),
            'customer' => $customer,
            'requirement' => $requirement,
            'price' => $latest?->unit_price !== null ? (float) $latest->unit_price : null,
            'volume' => (float) $order()->where('so.order_date', '>=', now()->subYear()->toDateString())->whereNotIn('so.status', ['DRAFT', 'CANCELLED'])->sum('sol.ordered_qty'),
            'openOrder' => $open,
            'dispatched' => $dispatched,
            'bom' => $lines,
            'materialCost' => $lines->contains(fn ($l) => $l['cost'] !== null) ? $lines->sum(fn ($l) => $l['cost'] ?? 0) : null,
            'previous' => $product->revisions->where('id', '!=', $revision?->id)->where('status', 'APPROVED')->sortByDesc('created_at')->first(),
        ];
    }

    /**
     * Archives a product: it leaves the product list, its counts and every product picker, but stays on the orders, lots and
     * quotes that used it and can be restored. A product with an open production order cannot be archived.
     */
    public function archive(Product $product): RedirectResponse
    {
        $open = ProductionOrder::open()->where('product_id', $product->id)->pluck('production_order_number');
        if ($open->isNotEmpty()) {
            return back()->withErrors(['archive' => "{$product->code} has open production orders (".$open->take(5)->implode(', ').($open->count() > 5 ? '…' : '').'). Complete or cancel them before archiving it.']);
        }
        $product->delete();

        return redirect()->route('products.index')->with('success', "{$product->code} archived. It is out of the product list and pickers; find it under Archived to restore it.");
    }

    public function restore(Product $product): RedirectResponse
    {
        $product->restore();

        return redirect()->route('products.show', $product)->with('success', "{$product->code} restored.");
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);   // "flg-1" and "FLG-1" are the same code
        }
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('products', 'code')->ignore($product->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'product_type' => ['required', Rule::in(['CASTING', 'CORE', 'OTHER'])],
            'base_uom' => ['required', 'string', 'max:20'],
            'nominal_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
            ...Product::tradeRules(),
        ]);

        $product->update([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'product_type' => $data['product_type'],
            'base_uom' => $data['base_uom'],
            'description' => $data['description'] ?? null,
            'active' => $data['active'],
            ...Arr::only($data, ['inventory_class', 'supply_type', 'tariff_code', 'tax_uom', 'origin_country']),
        ]);
        // The nominal weight is the current revision's net weight: changing it here changes that revision. Without a revision it is kept on the product.
        if ($current = $product->currentRevision()) {
            $current->update(['net_weight_kg' => $data['nominal_weight_kg'] ?? null]);
        } else {
            $product->update(['nominal_weight_kg' => $data['nominal_weight_kg'] ?? null]);
        }

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Product updated successfully.');
    }

    /** Sets or corrects the net weight and the engineering notes of a revision that already exists. */
    public function updateRevision(Request $request, Product $product, ProductRevision $revision): RedirectResponse
    {
        abort_unless($revision->product_id === $product->id, 404);
        $data = $request->validate([
            'net_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'engineering_notes' => ['nullable', 'string', 'max:5000'],
        ], ['net_weight_kg.gt' => 'The weight must be above zero. Leave it empty to clear it.']);

        $revision->update(['net_weight_kg' => $data['net_weight_kg'] ?? null, 'engineering_notes' => filled($data['engineering_notes'] ?? null) ? trim($data['engineering_notes']) : null]);

        return redirect()->route('products.show', $product)->with('success', "Revision {$revision->revision} saved.");
    }

    /** Sets which product-specific steps (e.g. grinding) a revision goes through, besides the default process. */
    public function updateRevisionSteps(Request $request, Product $product, ProductRevision $revision): RedirectResponse
    {
        abort_unless($revision->product_id === $product->id, 404);
        $data = $request->validate(['steps' => ['nullable', 'array'], 'steps.*' => ['uuid', Rule::exists('production_stage_definitions', 'id')->where('product_specific', true)]]);
        $revision->extraSteps()->sync($data['steps'] ?? []);

        return redirect()->route('products.show', $product)->with('success', "Extra steps of revision {$revision->revision} saved.");
    }

    public function storeRevision(Request $request, Product $product)
    {
        $data = $request->validate([
            'revision' => [
                'required', 'string', 'max:20',
                Rule::unique('product_revisions', 'revision')
                    ->where('product_id', $product->id),
            ],
            'status' => ['required', Rule::in(['DRAFT', 'APPROVED', 'OBSOLETE'])],
            'net_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'engineering_notes' => ['nullable', 'string'],
        ]);

        $product->revisions()->create($data);

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Product revision added successfully.');
    }
}
