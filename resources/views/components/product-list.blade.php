<?php

use App\Domains\Core\ModuleRegistry;
use App\Models\CastingLot;
use App\Models\IronGrade;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\ProductSpecification;
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
    public string $metal = '';

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
        if (in_array($property, ['search', 'metal', 'active', 'archived', 'customer', 'process'], true)) {
            $this->selected = [];
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'metal', 'active', 'customer', 'process', 'selected');
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
        $this->focused = $productId;
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
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('code', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%")
                ->orWhereHas('revisions.specifications', fn ($d) => $d->where('drawing_number', 'ilike', "%{$search}%"))))
            ->when($this->metal !== '', fn ($q) => $q->whereHas('revisions', fn ($r) => $r->where(fn ($w) => $w
                ->whereHas('specifications', fn ($d) => $d->whereIn('material_grade', $this->designationsFor($this->metal)))
                ->orWhereHas('customerRequirements', fn ($d) => $d->whereIn('customer_grade', $this->designationsFor($this->metal))))))
            ->when($this->active === '1', fn ($q) => $q->where('active', true))
            ->when($this->active === '0', fn ($q) => $q->where('active', false))
            ->when($this->active === 'dev', fn ($q) => $q->where('active', true)->whereHas('revisions', fn ($r) => $r->where('status', 'DRAFT'))
                ->whereDoesntHave('revisions', fn ($r) => $r->where('status', 'APPROVED')));
    }

    /**
     * The grade designations written on specifications and customer requirements that mean the given grade.
     *
     * @return array<int, string>
     */
    private function designationsFor(string $gradeId): array
    {
        $written = DB::table('product_specifications')->whereNotNull('material_grade')->pluck('material_grade')
            ->merge(DB::table('customer_product_requirements')->whereNotNull('customer_grade')->pluck('customer_grade'))->unique();

        return $written->filter(fn ($text) => (IronGrade::resolve($text)['grade']->id ?? null) === $gradeId)->values()->all();
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
            ->with('revisions.patternCavities.patternRevision.pattern.preferredStation')
            ->orderBy('code')
            ->paginate(20);
        $grades = $this->gradesFor($products->getCollection());

        // The pattern each product is made on: a current pattern revision with a cavity for its revision, else any.
        $patterns = $products->getCollection()->mapWithKeys(function (Product $product) use ($grades) {
            $cavities = ($grades[$product->id]['revision']?->patternCavities ?? collect())->filter(fn ($c) => $c->patternRevision?->pattern);

            return [$product->id => $cavities->sortByDesc(fn ($c) => $c->patternRevision->is_current)->first()?->patternRevision];
        });

        // The side panel shows the ticked row, else the first one on the page.
        $focus = $products->getCollection()->firstWhere('id', $this->focused) ?? $products->getCollection()->first();
        $summary = null;
        if ($focus) {
            $revision = $grades[$focus->id]['revision'] ?? null;
            $focusPattern = $patterns[$focus->id] ?? null;
            $summary = [
                'pattern' => $focusPattern,
                'cavity' => $revision?->patternCavities->firstWhere('pattern_revision_id', $focusPattern?->id)?->cavity_number,
                'drawing' => $revision ? ProductSpecification::where('product_revision_id', $revision->id)->whereNotNull('drawing_number')->orderByDesc('created_at')->first() : null,
                'order' => DB::table('sales_order_lines as sol')->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
                    ->where('sol.product_id', $focus->id)->orderByDesc('so.order_date')->value('so.order_number'),
                'shipped' => $this->tracksStock() ? (float) DB::table('dispatch_items as di')->join('dispatches as d', 'd.id', '=', 'di.dispatch_id')
                    ->join('packing_record_items as pri', 'pri.id', '=', 'di.packing_record_item_id')->join('casting_lots as cl', 'cl.id', '=', 'pri.casting_lot_id')
                    ->where('cl.product_id', $focus->id)->where('d.dispatched_at', '>=', now()->subYear())->sum('di.quantity_dispatched') : null,
                'routing' => $revision ? $revision->extraSteps()->pluck('name')->implode(' → ') : '',
            ];
        }

        return [
            'focus' => $focus,
            'summary' => $summary,
            'customers' => DB::table('customers as c')->join('parties as pa', 'pa.id', '=', 'c.party_id')->whereIn('c.id', DB::table('sales_orders')->select('customer_id'))->orderBy('pa.name')->pluck('pa.name', 'c.id'),
            'processes' => DB::table('patterns')->whereNotNull('sand_system')->where('sand_system', '!=', '')->distinct()->orderBy('sand_system')->pluck('sand_system'),
            'inDevelopment' => Product::where('active', true)->whereHas('revisions', fn ($r) => $r->where('status', 'DRAFT'))->whereDoesntHave('revisions', fn ($r) => $r->where('status', 'APPROVED'))->count(),
            'inactiveProducts' => Product::where('active', false)->count(),
            'products' => $products,
            'grades' => $grades,
            'patterns' => $patterns,
            'grades_list' => IronGrade::listed(),
            'totalProducts' => Product::count(),
            'activeProducts' => Product::where('active', true)->count(),
            'archivedProducts' => Product::onlyTrashed()->count(),
            'canManage' => (bool) auth()->user()?->hasPermission('master.manage'),
        ];
    }
};
?>

<div class="pc">
<style global>
    .pc { background: #fff; border: 1px solid #e6e7e9; border-radius: 10px; font-size: 13px; color: #1c1d1f; overflow: hidden; }
    .pc button, .pc select, .pc input { font: inherit; min-height: 0; }
    .pc-bar { display: flex; align-items: center; justify-content: space-between; padding: 14px 28px; border-bottom: 1px solid #e6e7e9; gap: 16px; flex-wrap: wrap; }
    .pc-bar-left { display: flex; align-items: center; gap: 16px; }
    .pc-tabs { display: flex; gap: 4px; color: #6b6e73; }
    .pc-tabs button { padding: 4px 10px; border-radius: 6px; background: none; border: 0; color: #6b6e73; cursor: pointer; }
    .pc-tabs button[aria-selected=true] { background: #f0f1f3; color: #1c1d1f; }
    .pc-btns { display: flex; gap: 8px; }
    .pc-btn { padding: 7px 14px; border-radius: 6px; background: #fff; border: 1px solid #d9dbde; color: #1c1d1f; text-decoration: none; cursor: pointer; }
    .pc-btn.primary { background: #2f5fd0; border-color: #2f5fd0; color: #fff; font-weight: 500; }
    .pc-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; }
    .pc-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .pc-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .pc-filters input[type=search] { flex: 1; max-width: 320px; padding: 7px 10px; border: 1px solid #d9dbde; border-radius: 6px; background: #fff; }
    .pc-filters select { padding: 6px 10px; border: 1px solid #d9dbde; border-radius: 6px; background: #fff; color: #1c1d1f; }
    .pc table { width: 100%; border-collapse: collapse; }
    .pc th { text-align: left; font-size: 12px; font-weight: 500; color: #6b6e73; padding: 8px 12px; border-bottom: 1px solid #e6e7e9; white-space: nowrap; }
    .pc td { padding: 10px 12px; border-bottom: 1px solid #e6e7e9; vertical-align: middle; }
    .pc tbody tr { cursor: pointer; }
    .pc tbody tr:last-child td { border-bottom: 0; }
    .pc tbody tr.on { background: #f5f8fe; }
    .pc th.r, .pc td.r { text-align: right; }
    .pc code { font-family: ui-monospace, Menlo, monospace; font-size: 12.5px; background: none; padding: 0; }
    .pc .name { font-weight: 500; color: #1c1d1f; text-decoration: none; }
    .pc .grade { color: #6b6e73; }
    .pc .pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; background: #f0f1f3; color: #55585d; }
    .pc .pill.ok { background: #e7f4ec; color: #1d7048; }
    .pc .pill.info { background: #eaf0fd; color: #2f5fd0; }
    .pc .pill.warn { background: #fbf0dc; color: #94610a; }
    .pc .muted { color: #6b6e73; }
    .pc-side { border-left: 1px solid #e6e7e9; padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 14px; }
    .pc-side h3 { margin: 0; font-size: 13px; font-weight: 600; }
    .pc-side h3 a { color: inherit; text-decoration: none; }
    .pc-side .row { display: flex; justify-content: space-between; gap: 12px; }
    .pc-side .row span:first-child { color: #6b6e73; }
    .pc-side .row span:last-child { text-align: right; }
    .pc-side .label { font-size: 12px; font-weight: 600; color: #6b6e73; text-transform: uppercase; letter-spacing: .06em; }
    .pc-side .stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .pc-side .stats .k { font-size: 12px; color: #6b6e73; }
    .pc-note { padding: 10px 12px; border: 1px solid #f0d9a8; border-radius: 8px; background: #fffaf0; color: #94610a; font-size: 12.5px; }
    .pc-link { background: none; border: 0; padding: 0 0 0 6px; color: #2f5fd0; cursor: pointer; }
    .pc-foot { display: flex; justify-content: space-between; align-items: center; color: #6b6e73; font-size: 12.5px; }
    .pc-foot a, .pc-foot button { color: #2f5fd0; background: none; border: 0; padding: 0; cursor: pointer; text-decoration: none; }
    @media (max-width: 1100px) { .pc-grid { grid-template-columns: 1fr; } .pc-side { border-left: 0; border-top: 1px solid #e6e7e9; } .pc-bar, .pc-main { padding-left: 16px; padding-right: 16px; } .pc-main { overflow-x: auto; } }
</style>

<div class="pc-bar">
    <div class="pc-bar-left">
        <strong>Products</strong>
        <div class="pc-tabs" role="tablist">
            <button type="button" role="tab" aria-selected="{{ ! $archived && $active === '1' ? 'true' : 'false' }}" wire:click="showStatus('1')">Active {{ Formats::number($activeProducts) }}</button>
            <button type="button" role="tab" aria-selected="{{ ! $archived && $active === 'dev' ? 'true' : 'false' }}" wire:click="showStatus('dev')">In development {{ Formats::number($inDevelopment) }}</button>
            <button type="button" role="tab" aria-selected="{{ ! $archived && $active === '0' ? 'true' : 'false' }}" wire:click="showStatus('0')">Obsolete {{ Formats::number($inactiveProducts) }}</button>
        </div>
    </div>
    <div class="pc-btns">
        <a class="pc-btn" href="{{ route('imports.create', ['entity' => 'product']) }}">Import</a>
        @if (Route::has('developments.create'))<a class="pc-btn" href="{{ route('developments.create', ['from' => 'products']) }}">Start development</a>@endif
        <a class="pc-btn primary" href="{{ route('products.create') }}">New product</a>
    </div>
</div>

<div class="pc-grid">
    <div class="pc-main">
        <div class="pc-filters" role="search">
            <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search code, name or drawing" aria-label="Search code, name or drawing" autocomplete="off">
            <select wire:model.live="customer" aria-label="Customer">
                <option value="">Customer: all</option>
                @foreach ($customers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select wire:model.live="metal" aria-label="Metal">
                <option value="">Metal: all</option>
                @foreach ($grades_list as $grade)<option value="{{ $grade->id }}">{{ $grade->code }}</option>@endforeach
            </select>
            <select wire:model.live="process" aria-label="Process">
                <option value="">Process: all</option>
                @foreach ($processes as $sand)<option value="{{ $sand }}">{{ $sand }}</option>@endforeach
            </select>
            @if ($search !== '' || $metal !== '' || $active !== '' || $customer !== '' || $process !== '')<button class="pc-btn" type="button" wire:click="resetFilters">Reset</button>@endif
            <span class="muted" wire:loading>Updating…</span>
        </div>

        @if ($products->isEmpty())
            <p class="muted">{{ $archived ? 'No archived products.' : 'No products found.' }}</p>
        @else
            <div style="margin:0 -12px">
                <table data-table="products">
                    <thead>
                        <tr>
                            <th>Code</th><th>Name</th><th>Customer</th><th>Grade</th><th>Process</th><th class="r">Casting kg</th><th>Revision</th><th>PPAP</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            @php
                                $g = $grades[$product->id] ?? null;
                                $sand = ($patterns[$product->id] ?? null)?->pattern->madeForSand();
                                $isDraft = $g && $g['revision']?->status === 'DRAFT' && ! $product->revisions->contains('status', 'APPROVED');
                            @endphp
                            <tr wire:key="product-{{ $product->id }}" wire:click="focus('{{ $product->id }}')" @class(['on' => $focus && (string) $focus->id === (string) $product->id])>
                                <td><code>{{ $product->code }}</code></td>
                                <td><a class="name" href="{{ route('products.show', $product) }}" wire:click.stop>{{ $product->name }}</a></td>
                                <td>{{ $product->customer_name ?? '—' }}</td>
                                <td><span class="grade">{{ $g['grade']->code ?? '—' }}</span></td>
                                <td>{{ $sand ?: '—' }}</td>
                                <td class="r">{{ $product->nominal_weight_kg !== null ? Formats::number((float) $product->nominal_weight_kg, 2) : '—' }}</td>
                                <td>{{ $g['revision']->revision ?? '—' }}</td>
                                <td><span class="muted">—</span></td>
                                <td>
                                    @if ($archived)<span class="pill">Archived</span> <form method="POST" action="{{ route('products.restore', $product) }}" style="display:inline" wire:click.stop>@csrf<button class="pc-link" type="submit">Restore</button></form>
                                    @elseif (! $product->active)<span class="pill">Obsolete</span>
                                    @elseif ($isDraft)<span class="pill info">In development</span>
                                    @else<span class="pill ok">Active</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pc-foot">
                <span>Showing {{ $products->firstItem() }}–{{ $products->lastItem() }} of {{ $products->total() }}</span>
                <span class="pagination-links">{{ $products->links() }}</span>
            </div>
        @endif
        <div class="pc-foot">
            <button type="button" wire:click="$toggle('archived')">{{ $archived ? 'Back to current products' : $archivedProducts.' archived' }}</button>
            <button type="button" wire:click="export">Export CSV</button>
        </div>
    </div>

    @if ($focus)
        <aside class="pc-side" aria-label="Summary of {{ $focus->name }}">
            <h3><a href="{{ route('products.show', $focus) }}">{{ $focus->code }} · {{ $focus->name }}</a></h3>
            <div class="row"><span>Customer</span><span>{{ $focus->customer_name ?? '—' }}</span></div>
            <div class="row"><span>Drawing</span><span>{{ $summary['drawing'] ? $summary['drawing']->drawing_number.($summary['drawing']->drawing_revision ? ' rev '.$summary['drawing']->drawing_revision : '') : '—' }}</span></div>
            <div class="row"><span>Pattern</span><span>@if ($summary['pattern'])<code>{{ $summary['pattern']->pattern->code }}</code>@if ($summary['cavity']) cavity {{ $summary['cavity'] }}@endif @else — @endif</span></div>
            <div class="row"><span>Price</span><span>{{ $focus->last_price !== null ? Formats::money($focus->last_price) : '—' }}{{ $summary['order'] ? ' · '.$summary['order'] : '' }}</span></div>
            <div class="label">Last 12 months</div>
            <div class="stats">
                <div><div class="k">Shipped</div><div>{{ $summary['shipped'] !== null ? Formats::number($summary['shipped'], 0).' pcs' : '—' }}</div></div>
                <div><div class="k">Scrap</div><div>—</div></div>
                <div><div class="k">Margin</div><div>—</div></div>
            </div>
            <div class="label">Routing</div>
            <div>{{ $summary['routing'] !== '' ? $summary['routing'] : '—' }}</div>
        </aside>
    @endif
</div>
</div>
