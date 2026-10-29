<?php

use App\Domains\Core\ModuleRegistry;
use App\Models\CastingLot;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Services\Planning\HeatPlanner;
use App\Support\Formats;
use App\Support\TaxJurisdiction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The product catalogue: search, category, stock and status filters apply as they are typed, and the page and the archived switch
 * change without a reload. Stock is packed castings not yet dispatched; the price is that of the product's latest sales order line.
 * The state is kept in the address, so a filtered list can be bookmarked or shared and the back button works.
 */
new class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $stock = '';

    #[Url(except: '')]
    public string $active = '';

    #[Url(except: '')]
    public string $customer = '';

    #[Url(except: '')]
    public string $process = '';

    /** The product whose summary shows in the side panel. */
    public ?string $focused = null;

    #[Url(except: false)]
    public bool $archived = false;

    /** @var array<int, string> */
    public array $selected = [];

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'category', 'stock', 'active', 'archived', 'customer', 'process'], true)) {
            $this->selected = [];
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'category', 'stock', 'active', 'customer', 'process', 'selected');
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'livewire.pagination';
    }

    /** The status tabs: active products, those still in development (only draft revisions), and inactive (obsolete) ones. */
    public function showStatus(string $status): void
    {
        $this->archived = false;
        $this->active = $status;
        $this->selected = [];
        $this->resetPage();
    }

    public function focus(string $productId): void
    {
        $this->focused = $this->focused === $productId ? null : $productId;
    }

    public function toggleActive(string $productId): void
    {
        $this->authorizeChanges();
        $product = Product::findOrFail($productId);
        $product->update(['active' => ! $product->active]);
    }

    /** Sets the ticked products active or inactive. */
    public function setActive(bool $active): void
    {
        $this->authorizeChanges();
        Product::whereIn('id', $this->selected)->update(['active' => $active]);
        $this->selected = [];
    }

    /** Downloads every product that matches the filters, not only this page, as CSV. */
    public function export(): StreamedResponse
    {
        $products = $this->filtered()->with('revisions')->orderBy('code')->get();
        $grades = $this->gradesFor($products);

        return response()->streamDownload(function () use ($products, $grades) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Name', 'Type', 'Inventory class', TaxJurisdiction::tariffLabel(), 'Tax unit', 'Origin', 'Grade', 'Stock (pcs)', 'Last price', 'Nominal weight (kg)', 'Status']);
            foreach ($products as $product) {
                fputcsv($out, [
                    $product->code, $product->name, $product->product_type, $product->inventoryClass()->label(), $product->tariff_code, $product->tax_uom, $product->origin_country,
                    $grades[$product->id]['grade']?->code ?? '',
                    $product->stock_qty !== null ? (float) $product->stock_qty : '', $product->last_price !== null ? (float) $product->last_price : '',
                    $product->nominal_weight_kg !== null ? (float) $product->nominal_weight_kg : '', $product->active ? 'ACTIVE' : 'INACTIVE',
                ]);
            }
            fclose($out);
        }, 'products-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeChanges(): void
    {
        abort_unless(auth()->user()?->hasPermission('master.manage'), 403);
    }

    private function tracksStock(): bool
    {
        return app(ModuleRegistry::class)->isEnabled('dispatch');
    }

    /** Products with their stock on hand and last sales price added as columns, narrowed by every filter. */
    private function filtered(): Builder
    {
        $search = trim($this->search);
        $tracksStock = $this->tracksStock();

        return Product::query()
            ->select('products.*')
            ->when($tracksStock, fn ($q) => $q->selectSub($this->stockQuery(), 'stock_qty'), fn ($q) => $q->selectRaw('null as stock_qty'))
            ->selectSub(DB::table('sales_order_lines as sol')->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
                ->whereColumn('sol.product_id', 'products.id')->orderByDesc('so.order_date')->orderByDesc('sol.created_at')->limit(1)->select('sol.unit_price'), 'last_price')
            ->selectSub($this->customerQuery()->select('pa.name'), 'customer_name')
            ->when($this->archived, fn ($q) => $q->onlyTrashed())
            ->when($this->customer !== '', fn ($q) => $q->whereExists(fn ($e) => $e->select(DB::raw(1))->from('sales_order_lines as sol2')->join('sales_orders as so2', 'so2.id', '=', 'sol2.sales_order_id')
                ->whereColumn('sol2.product_id', 'products.id')->where('so2.customer_id', $this->customer)))
            ->when($this->process !== '', fn ($q) => $q->whereHas('revisions.patternCavities.patternRevision.pattern', fn ($p) => $p->where('sand_system', $this->process)))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('code', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%")))
            ->when($this->category !== '', fn ($q) => $q->where('product_type', $this->category))
            ->when($tracksStock && $this->stock === 'in', fn ($q) => $q->where($this->stockQuery(), '>', 0))
            ->when($tracksStock && $this->stock === 'out', fn ($q) => $q->where($this->stockQuery(), '<=', 0))
            ->when($this->active === '1', fn ($q) => $q->where('active', true))
            ->when($this->active === '0', fn ($q) => $q->where('active', false))
            ->when($this->active === 'dev', fn ($q) => $q->where('active', true)->whereHas('revisions', fn ($r) => $r->where('status', 'DRAFT'))
                ->whereDoesntHave('revisions', fn ($r) => $r->where('status', 'APPROVED')));
    }

    /** The customer of a product's latest sales order line. */
    private function customerQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('sales_order_lines as sl')->join('sales_orders as so', 'so.id', '=', 'sl.sales_order_id')->join('customers as c', 'c.id', '=', 'so.customer_id')->join('parties as pa', 'pa.id', '=', 'c.party_id')
            ->whereColumn('sl.product_id', 'products.id')->orderByDesc('so.order_date')->orderByDesc('sl.created_at')->limit(1);
    }

    /** Pieces of a product packed and not yet dispatched. */
    private function stockQuery(): \Illuminate\Database\Query\Builder
    {
        $packed = DB::table('packing_record_items as pri')->join('casting_lots as cl', 'cl.id', '=', 'pri.casting_lot_id')
            ->whereColumn('cl.product_id', 'products.id')->selectRaw('coalesce(sum(pri.quantity_packed), 0)');
        $dispatched = DB::table('dispatch_items as di')->join('packing_record_items as pri', 'pri.id', '=', 'di.packing_record_item_id')
            ->join('casting_lots as cl', 'cl.id', '=', 'pri.casting_lot_id')->whereColumn('cl.product_id', 'products.id')
            ->selectRaw('coalesce(sum(di.quantity_dispatched), 0)');

        return DB::query()->selectRaw('('.$packed->toSql().') - ('.$dispatched->toSql().')', [...$packed->getBindings(), ...$dispatched->getBindings()]);
    }

    /**
     * The revision each product is described by (its latest approved one, else its latest one that is not obsolete) and the grade it is made to.
     *
     * @return \Illuminate\Support\Collection<string, array{grade: ?\App\Models\IronGrade, revision: ?ProductRevision}>
     */
    private function gradesFor($products)
    {
        $planner = app(HeatPlanner::class);

        return $products->mapWithKeys(function (Product $product) use ($planner) {
            $revisions = $product->revisions->sortByDesc('created_at');
            $revision = $revisions->firstWhere('status', 'APPROVED') ?? $revisions->firstWhere('status', '!=', 'OBSOLETE') ?? $revisions->first();

            return [$product->id => ['grade' => $revision ? $planner->gradeFor($revision) : null, 'revision' => $revision]];
        });
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $products = $this->filtered()
            ->withCount('revisions')->with('revisions.patternCavities.patternRevision.pattern.preferredStation')
            ->orderBy('code')
            ->paginate(20);
        $grades = $this->gradesFor($products->getCollection());

        // The pattern each product is made on: a current pattern revision with a cavity for its revision, else any.
        $patterns = $products->getCollection()->mapWithKeys(function (Product $product) use ($grades) {
            $cavities = ($grades[$product->id]['revision']?->patternCavities ?? collect())->filter(fn ($c) => $c->patternRevision?->pattern);

            return [$product->id => $cavities->sortByDesc(fn ($c) => $c->patternRevision->is_current)->first()?->patternRevision];
        });

        // The day each product was last poured: its newest casting lot.
        $lastCast = CastingLot::whereIn('product_id', $products->pluck('id'))->whereNotNull('created_from_pour_at')
            ->groupBy('product_id')->selectRaw('product_id, max(created_from_pour_at) as last_cast')->pluck('last_cast', 'product_id')
            ->map(fn ($d) => Carbon::parse($d));

        $tracksStock = $this->tracksStock();
        $onHand = $tracksStock
            ? Product::query()->select('products.id')->selectSub($this->stockQuery(), 'stock_qty')
                ->selectSub(DB::table('sales_order_lines as sol')->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
                    ->whereColumn('sol.product_id', 'products.id')->orderByDesc('so.order_date')->orderByDesc('sol.created_at')->limit(1)->select('sol.unit_price'), 'last_price')
                ->where('active', true)->get()
            : collect();
        $monthSales = DB::table('sales_order_lines as sol')->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
            ->whereBetween('so.order_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->whereNotIn('so.status', ['DRAFT', 'CANCELLED'])
            ->selectRaw('coalesce(sum(sol.ordered_qty * sol.unit_price), 0) as value, count(distinct sol.product_id) as products')->first();

        $focus = $this->focused ? $products->getCollection()->firstWhere('id', $this->focused) : null;
        $focusPattern = $focus ? ($patterns[$focus->id] ?? null) : null;
        $summary = $focus ? [
            'shipped' => $tracksStock ? (float) DB::table('dispatch_items as di')->join('dispatches as d', 'd.id', '=', 'di.dispatch_id')
                ->join('packing_record_items as pri', 'pri.id', '=', 'di.packing_record_item_id')->join('casting_lots as cl', 'cl.id', '=', 'pri.casting_lot_id')
                ->where('cl.product_id', $focus->id)->where('d.dispatched_at', '>=', now()->subYear())->sum('di.quantity_dispatched') : null,
            'lastOrder' => DB::table('sales_order_lines as sol')->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
                ->where('sol.product_id', $focus->id)->orderByDesc('so.order_date')->value('so.order_number'),
            'cavity' => $grades[$focus->id]['revision']?->patternCavities->firstWhere('pattern_revision_id', $focusPattern?->id)?->cavity_number,
        ] : null;

        return [
            'focus' => $focus,
            'summary' => $summary,
            'focusPattern' => $focusPattern,
            'customers' => DB::table('customers as c')->join('parties as pa', 'pa.id', '=', 'c.party_id')->whereIn('c.id', DB::table('sales_orders')->select('customer_id'))->orderBy('pa.name')->pluck('pa.name', 'c.id'),
            'processes' => DB::table('patterns')->whereNotNull('sand_system')->where('sand_system', '!=', '')->distinct()->orderBy('sand_system')->pluck('sand_system'),
            'inDevelopment' => Product::where('active', true)->whereHas('revisions', fn ($r) => $r->where('status', 'DRAFT'))->whereDoesntHave('revisions', fn ($r) => $r->where('status', 'APPROVED'))->count(),
            'inactiveProducts' => Product::where('active', false)->count(),
            'products' => $products,
            'grades' => $grades,
            'patterns' => $patterns,
            'lastCast' => $lastCast,
            'tracksStock' => $tracksStock,
            'categories' => Product::query()->distinct()->orderBy('product_type')->pluck('product_type'),
            'stockValue' => $onHand->sum(fn ($p) => max(0, (float) $p->stock_qty) * (float) $p->last_price),
            'productsInStock' => $onHand->filter(fn ($p) => (float) $p->stock_qty > 0)->count(),
            'outOfStock' => $onHand->filter(fn ($p) => (float) $p->stock_qty <= 0)->count(),
            'addedThisMonth' => Product::where('created_at', '>=', now()->startOfMonth())->count(),
            'monthSales' => (float) $monthSales->value,
            'monthSalesProducts' => (int) $monthSales->products,
            'totalProducts' => Product::count(),
            'activeProducts' => Product::where('active', true)->count(),
            'archivedProducts' => Product::onlyTrashed()->count(),
            'canManage' => (bool) auth()->user()?->hasPermission('master.manage'),
        ];
    }
};
?>

<div class="catalog">
<style global>
    .catalog .metric { position: relative; }
    .catalog .metric-icon { position: absolute; top: 16px; right: 16px; width: 40px; height: 40px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--accent) 10%, transparent); color: var(--accent); }
    .catalog .metric-icon.warn { background: color-mix(in srgb, var(--warning) 14%, transparent); color: var(--warning); }
    .catalog .metric-icon.ok { background: color-mix(in srgb, var(--success) 12%, transparent); color: var(--success); }
    .catalog .metric-note { font-size: 12.5px; margin-top: 8px; }
    .catalog .cat-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 16px; }
    .catalog .cat-filters input[type=search] { min-width: 220px; }
    .catalog .cat-filters select { min-width: 150px; }
    .catalog .cat-bulk { margin-left: auto; display: flex; gap: 14px; align-items: center; font-size: 12px; letter-spacing: .4px; text-transform: uppercase; color: var(--muted); }
    .catalog .cat-bulk button { background: none; border: 0; padding: 0; min-height: 0; color: var(--accent); text-decoration: underline; text-transform: none; letter-spacing: 0; font-size: 13.5px; cursor: pointer; }
    .catalog .cat-bulk button:disabled { color: var(--muted); text-decoration: none; cursor: default; }
    .catalog .cat-table thead th { background: var(--accent); color: #fff; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; padding-top: 14px; padding-bottom: 14px; }
    .catalog .cat-table td { vertical-align: middle; padding-top: 14px; padding-bottom: 14px; }
    .catalog .cat-name { font-size: 15px; font-weight: 600; color: var(--text); }
    .catalog .cat-sub { color: var(--muted); font-size: 12.5px; margin: 2px 0 6px; }
    .catalog .cat-chips { display: flex; flex-wrap: wrap; gap: 5px; max-width: 420px; }
    .catalog .cat-chip { border: 1px solid var(--border); border-radius: 6px; padding: 2px 8px; font-size: 12px; color: var(--muted); white-space: nowrap; }
    .catalog .cat-photo { display: flex; gap: 14px; align-items: flex-start; }
    .catalog .cat-photo .product-cell > a, .catalog .cat-photo .product-cell-none { width: 64px; height: 64px; }
    .catalog .cat-pill { display: inline-block; border-radius: 6px; padding: 3px 9px; font-size: 12px; font-weight: 600; white-space: nowrap; background: color-mix(in srgb, var(--accent) 10%, transparent); color: var(--accent-dark, var(--accent)); }
    .catalog .cat-stock strong { display: block; font-size: 15px; }
    .catalog .cat-stock .in, .catalog .cat-stock .out { display: inline-block; margin-top: 4px; border-radius: 6px; padding: 2px 8px; font-size: 11.5px; font-weight: 600; }
    .catalog .cat-stock .in { background: #e4f5eb; color: #176b43; }
    .catalog .cat-stock .out { background: #fce8e6; color: #a12820; }
    .catalog .cat-switch { position: relative; width: 40px; height: 22px; min-height: 0; padding: 0; border: 0; border-radius: 11px; background: var(--border); cursor: pointer; transition: background .15s; }
    .catalog .cat-switch::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: transform .15s; }
    .catalog .cat-switch[aria-checked=true] { background: var(--accent); }
    .catalog .cat-switch[aria-checked=true]::after { transform: translateX(18px); }
    .catalog .cat-switch:disabled { cursor: default; opacity: .7; }
    .catalog .cat-actions { white-space: nowrap; }
    .catalog .cat-actions form { display: inline; }
    .catalog .cat-tabs { display: flex; gap: 4px; padding: 12px 16px 0; }
    .catalog .cat-tabs button { background: none; border: 0; border-radius: 6px; padding: 5px 12px; min-height: 0; font-size: 13px; color: var(--muted); cursor: pointer; }
    .catalog .cat-tabs button[aria-selected=true] { background: color-mix(in srgb, var(--text) 8%, transparent); color: var(--text); }
    .catalog .cat-peek { background: none; border: 0; padding: 0 0 0 6px; min-height: 0; font-size: 12px; color: var(--accent); cursor: pointer; }
    .catalog .cat-focused { background: color-mix(in srgb, var(--accent) 6%, transparent); }
    .catalog .cat-split.has-panel { display: grid; grid-template-columns: minmax(0, 1fr) 320px; align-items: start; }
    .catalog .cat-side { border-left: 1px solid var(--border); padding: 18px 20px; display: flex; flex-direction: column; gap: 12px; font-size: 13px; }
    .catalog .cat-side-head { display: flex; justify-content: space-between; gap: 8px; }
    .catalog .cat-side dl { display: grid; grid-template-columns: auto 1fr; gap: 8px 14px; margin: 0; }
    .catalog .cat-side dt { color: var(--muted); }
    .catalog .cat-side dd { margin: 0; text-align: right; }
    @media (max-width: 1100px) { .catalog .cat-split.has-panel { grid-template-columns: 1fr; } .catalog .cat-side { border-left: 0; border-top: 1px solid var(--border); } }
    .catalog .num { text-align: right; white-space: nowrap; }
    @media (max-width: 768px) { .catalog .cat-bulk { margin-left: 0; } .catalog .cat-filters input[type=search] { min-width: 0; width: 100%; } }
</style>

<div class="page-heading">
    <div>
        <h1>Product catalogue</h1>
        <p>The castings you sell: stock waiting to ship, latest price, grade, pattern and yield at a glance.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('imports.create', ['entity' => 'product']) }}">{!! \App\Support\NavIcons::get('upload', 15) !!} Import</a>
        <button class="btn" type="button" wire:click="export">{!! \App\Support\NavIcons::get('file-text', 15) !!} Export</button>
        <button class="btn" type="button" wire:click="$toggle('archived')" aria-pressed="{{ $archived ? 'true' : 'false' }}">{!! \App\Support\NavIcons::get('archive', 15) !!} {{ $archived ? 'Current products' : $archivedProducts.' archived' }}</button>
        @if (Route::has('developments.create'))<a class="btn" href="{{ route('developments.create', ['from' => 'products']) }}">Start development</a>@endif
        <a class="btn btn-primary" href="{{ route('products.create') }}">+ Add product</a>
    </div>
</div>

<div class="panel">
    <div class="cat-tabs" role="tablist">
        <button type="button" role="tab" aria-selected="{{ ! $archived && ! in_array($active, ['0', 'dev'], true) ? 'true' : 'false' }}" wire:click="showStatus('')">All {{ Formats::number($totalProducts) }}</button>
        <button type="button" role="tab" aria-selected="{{ ! $archived && $active === '1' ? 'true' : 'false' }}" wire:click="showStatus('1')">Active {{ Formats::number($activeProducts) }}</button>
        <button type="button" role="tab" aria-selected="{{ ! $archived && $active === 'dev' ? 'true' : 'false' }}" wire:click="showStatus('dev')">In development {{ Formats::number($inDevelopment) }}</button>
        <button type="button" role="tab" aria-selected="{{ ! $archived && $active === '0' ? 'true' : 'false' }}" wire:click="showStatus('0')">Obsolete {{ Formats::number($inactiveProducts) }}</button>
    </div>
    <div class="cat-filters" role="search">
        <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search products…" aria-label="Search code or name" autocomplete="off">
        <select wire:model.live="category" aria-label="Category">
            <option value="">All categories</option>
            @foreach ($categories as $type)<option value="{{ $type }}">{{ \Illuminate\Support\Str::headline(strtolower($type)) }}</option>@endforeach
        </select>
        @if ($tracksStock)
            <select wire:model.live="stock" aria-label="Stock">
                <option value="">All stock</option>
                <option value="in">In stock</option>
                <option value="out">Out of stock</option>
            </select>
        @endif
        <select wire:model.live="customer" aria-label="Customer">
            <option value="">Customer: all</option>
            @foreach ($customers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
        </select>
        <select wire:model.live="process" aria-label="Process">
            <option value="">Process: all</option>
            @foreach ($processes as $sand)<option value="{{ $sand }}">{{ $sand }}</option>@endforeach
        </select>
        @if ($search !== '' || $category !== '' || $stock !== '' || $active !== '' || $customer !== '' || $process !== '')<button class="btn" type="button" wire:click="resetFilters">Reset</button>@endif
        <span class="muted small" wire:loading>Updating…</span>
        @if ($canManage && ! $archived)
            <div class="cat-bulk">
                <span>Bulk actions{{ $selected ? ' ('.count($selected).')' : '' }}:</span>
                <button type="button" wire:click="setActive(true)" @disabled(! $selected)>Activate</button>
                <button type="button" wire:click="setActive(false)" @disabled(! $selected)>Deactivate</button>
            </div>
        @endif
    </div>

    @if ($archived)
        <div class="panel-header"><h2>Archived products</h2><span class="muted small"><a href="#" wire:click.prevent="$set('archived', false)">Back to current products</a></span></div>
    @endif

    @if ($products->isEmpty())
        <div class="empty-state">
            <strong>{{ $archived ? 'No archived products' : 'No products found' }}</strong>
            <p>Create your first product to start building the production workflow.</p>
            <a class="btn btn-primary" href="{{ route('products.create') }}">Create product</a>
        </div>
    @else
        <div style="display:flex;justify-content:flex-end;margin-bottom:8px"><x-column-picker table="products" :columns="['code' => 'Code', 'customer' => 'Customer', 'category' => 'Category', 'stock' => 'Stock level', 'price' => 'Unit price', 'weight' => 'Weight', 'status' => 'Status']" /></div>
        <div class="cat-split @if ($focus) has-panel @endif">
        <div class="table-wrap">
            <table class="cat-table" data-table="products">
                <thead>
                    <tr>
                        @if ($canManage && ! $archived)<th style="width:28px"><input type="checkbox" aria-label="Tick every product on this page" x-on:change="$wire.set('selected', $event.target.checked ? @js($products->pluck('id')->map(fn ($id) => (string) $id)->values()) : [])" @checked(count($selected) === $products->count())></th>@endif
                        <th>Name</th>
                        <th data-col="code">Code</th>
                        <th data-col="customer">Customer</th>
                        <th data-col="category">Category</th>
                        @if ($tracksStock)<th data-col="stock">Stock level</th>@endif
                        <th class="num" data-col="price" title="Price on the product's latest sales order line">Unit price</th>
                        <th class="num" data-col="weight">Weight</th>
                        <th data-col="status">Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        @php
                            $g = $grades[$product->id] ?? null;
                            $patternRevision = $patterns[$product->id] ?? null;
                            $sand = $patternRevision?->pattern->madeForSand();
                            $yield = $patternRevision?->yieldPercent();
                        @endphp
                        <tr wire:key="product-{{ $product->id }}" @class(['cat-focused' => $focused === (string) $product->id])>
                            @if ($canManage && ! $archived)<td><input type="checkbox" value="{{ $product->id }}" wire:model.live="selected" aria-label="Tick {{ $product->name }}"></td>@endif
                            <td>
                                <div class="cat-photo">
                                    @php $photo = \App\Models\Attachment::coverFor('product', (string) $product->id); @endphp
                                    <span class="product-cell">
                                        @if ($photo)<a href="{{ route('attachments.download', $photo) }}" data-lightbox="product-cells" data-caption="{{ $product->name }} · {{ $product->code }}"><img src="{{ route('attachments.download', $photo) }}" alt="" loading="lazy"></a>
                                        @else<span class="product-cell-none" title="No photo of this product yet">{!! \App\Support\NavIcons::get('layers', 22) !!}</span>@endif
                                    </span>
                                    <div>
                                        <a class="cat-name" href="{{ route('products.show', $product) }}">{{ $product->name }}</a> <button type="button" class="cat-peek" wire:click="focus('{{ $product->id }}')" aria-pressed="{{ $focused === (string) $product->id ? 'true' : 'false' }}" title="Show a summary of {{ $product->name }}">Summary</button>
                                        <div class="cat-sub">@if ($product->tariff_code){{ \App\Support\TaxJurisdiction::tariffLabel() }}: {{ $product->tariff_code }} · @endif @if ($g && $g['revision'])Rev {{ $g['revision']->revision }}@endif{{ isset($lastCast[$product->id]) ? ' · last cast '.$lastCast[$product->id]->displayDate() : '' }}</div>
                                        <div class="cat-chips">
                                            @if ($sand)<span class="cat-chip">{{ $sand }}</span>@endif
                                            @if ($g && $g['grade'])<span class="cat-chip" title="{{ $g['grade']->name }}">{{ $g['grade']->code }}</span>@else<span class="cat-chip" title="Set the grade on the product's Iron grade tab">No grade</span>@endif
                                            @if ($patternRevision)<span class="cat-chip" title="Pattern">{{ $patternRevision->pattern->code }}</span>@endif
                                            @if ($yield !== null)<span class="cat-chip" title="Casting weight as a share of the poured weight">{{ Formats::number($yield, 1) }} %</span>@endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="muted" data-col="code" style="white-space:nowrap">{{ $product->code }}</td>
                            <td data-col="customer">{{ $product->customer_name ?? '—' }}</td>
                            <td data-col="category"><span class="cat-pill">{{ \Illuminate\Support\Str::headline(strtolower($product->product_type)) }}</span></td>
                            @if ($tracksStock)
                                <td class="cat-stock" data-col="stock">
                                    <strong>{{ Formats::number(max(0, (float) $product->stock_qty), 0) }} {{ $product->base_uom === 'piece' ? 'pcs' : $product->base_uom }}</strong>
                                    @if ((float) $product->stock_qty > 0)<span class="in">● In stock</span>@else<span class="out">● Out of stock</span>@endif
                                </td>
                            @endif
                            <td class="num" data-col="price">{{ $product->last_price !== null ? Formats::money($product->last_price) : '—' }}</td>
                            <td class="num" data-col="weight">{{ $product->nominal_weight_kg !== null ? Formats::number((float) $product->nominal_weight_kg, 2).' kg' : '—' }}</td>
                            <td data-col="status">
                                @if ($archived)
                                    <span class="status status-cancelled">ARCHIVED</span>
                                @else
                                    <button type="button" class="cat-switch" role="switch" aria-checked="{{ $product->active ? 'true' : 'false' }}" aria-label="{{ $product->name }} {{ $product->active ? 'active' : 'inactive' }}" title="{{ $product->active ? 'Active: click to deactivate' : 'Inactive: click to activate' }}" wire:click="toggleActive('{{ $product->id }}')" @disabled(! $canManage)></button>
                                @endif
                            </td>
                            <td class="cat-actions">
                                @if ($archived)
                                    <form method="POST" action="{{ route('products.restore', $product) }}">@csrf<button class="btn btn-small" type="submit">Restore</button></form>
                                @else
                                    @include('partials.icon-button', ['type' => 'edit', 'name' => $product->name, 'href' => route('products.edit', $product)])
                                    @if ($canManage)
                                        <form method="POST" action="{{ route('products.archive', $product) }}">@csrf @method('DELETE')
                                            @include('partials.icon-button', ['type' => 'delete', 'word' => 'Archive', 'name' => $product->name, 'confirm' => "Archive {$product->code}? It leaves the list and pickers and can be restored."])
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($focus)
            @php $rev = $grades[$focus->id]['revision'] ?? null; @endphp
            <aside class="cat-side" aria-label="Summary of {{ $focus->name }}">
                <div class="cat-side-head"><div><strong>{{ $focus->code }} · {{ $focus->name }}</strong></div><button type="button" class="btn btn-small" wire:click="focus('{{ $focus->id }}')" aria-label="Close summary">✕</button></div>
                <dl>
                    <dt>Customer</dt><dd>{{ $focus->customer_name ?? '—' }}</dd>
                    <dt>Grade</dt><dd>{{ $grades[$focus->id]['grade']?->code ?? 'No grade' }}</dd>
                    <dt>Revision</dt><dd>{{ $rev?->revision ?? '—' }}{{ $rev ? ' · '.\Illuminate\Support\Str::lower($rev->status) : '' }}</dd>
                    <dt>Pattern</dt><dd>@if ($focusPattern){{ $focusPattern->pattern->code }}@if ($summary['cavity']) · cavity {{ $summary['cavity'] }}@endif @else — @endif</dd>
                    <dt>Price</dt><dd>{{ $focus->last_price !== null ? Formats::money($focus->last_price) : '—' }}{{ $summary['lastOrder'] ? ' · '.$summary['lastOrder'] : '' }}</dd>
                    @if ($tracksStock)<dt>Shipped, last 12 months</dt><dd>{{ Formats::number($summary['shipped'], 0) }} pcs</dd>@endif
                </dl>
                <a class="btn btn-primary" href="{{ route('products.show', $focus) }}">Open product</a>
            </aside>
        @endif
        </div>
        <div class="pagination">
            <div class="muted small">
                Showing {{ $products->firstItem() }}–{{ $products->lastItem() }}
                of {{ $products->total() }}
            </div>
            <div class="pagination-links">{{ $products->links() }}</div>
        </div>
    @endif
</div>
</div>
