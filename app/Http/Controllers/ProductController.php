<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
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
        ]);
    }

    public function create()
    {
        return view('products.create', [
            'product' => new Product(),
            'revisionStatuses' => ['DRAFT', 'APPROVED', 'OBSOLETE'],
        ]);
    }

    public function store(Request $request)
    {
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

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
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
