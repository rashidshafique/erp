<?php

namespace App\Http\Controllers;

use App\Models\Pattern;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Services\PatternCavityMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $archived = $request->boolean('archived');
        $products = Product::query()
            ->when($archived, fn ($q) => $q->onlyTrashed())
            ->withCount('revisions')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->input('search'));

                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%");
                });
            })
            ->when($request->input('active') === '1', fn ($q) => $q->where('active', true))
            ->when($request->input('active') === '0', fn ($q) => $q->where('active', false))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'totalProducts' => Product::count(),
            'activeProducts' => Product::where('active', true)->count(),
            'inactiveProducts' => Product::where('active', false)->count(),
            'archivedProducts' => Product::onlyTrashed()->count(),
            'archived' => $archived,
        ]);
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
     * current one unless a revision is picked). A pattern with no revision gets its first one here. Without a cavity number it
     * takes the revision's first free cavity.
     */
    public function linkPattern(Request $request, Product $product, PatternCavityMap $map): RedirectResponse
    {
        $data = $request->validate([
            'pattern_id' => ['required', 'uuid', Rule::exists('patterns', 'id')->whereNull('deleted_at')],
            'pattern_revision_id' => ['nullable', 'uuid'],
            'product_revision_id' => ['required', 'uuid', Rule::exists('product_revisions', 'id')->where('product_id', $product->id)],
            'cavity_number' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ], ['pattern_id.required' => 'Choose a pattern.', 'product_revision_id.required' => 'Choose which product revision the pattern makes.']);

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

            $taken = $revision->cavityMap()->pluck('cavity_number')->all();
            $number = $data['cavity_number'] ?? null;
            if ($number === null) {
                $limit = $revision->cavities_per_mould ?? (count($taken) ? max($taken) + 1 : 1);
                $number = collect(range(1, max(1, (int) $limit)))->first(fn ($n) => ! in_array($n, $taken, true));
                if ($number === null) {
                    throw ValidationException::withMessages(['cavity_number' => "Every cavity of {$revision->label()} already has a product. Raise the cavity count on the pattern page, or pick another revision."]);
                }
            }

            $cavity = ['cavity_number' => (int) $number, 'product_revision_id' => $data['product_revision_id'], 'cavity_role' => 'WHOLE', 'pieces_per_product' => 1];
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

        return view('products.show', compact('product'));
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
        ]);

        $product->update([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'product_type' => $data['product_type'],
            'base_uom' => $data['base_uom'],
            'nominal_weight_kg' => $data['nominal_weight_kg'] ?? null,
            'description' => $data['description'] ?? null,
            'active' => $data['active'],
        ]);

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Product updated successfully.');
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
