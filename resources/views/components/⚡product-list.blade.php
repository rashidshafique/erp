<?php

use App\Models\CastingLot;
use App\Models\Product;
use App\Services\Planning\HeatPlanner;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The product list: search and the active filter apply as they are typed, and the page and the archived switch change without a
 * reload. The page's title, breadcrumb and menu stay in the Blade page this sits in. The state is kept in the address, so a
 * filtered list can be bookmarked or shared and the back button works.
 */
new class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $active = '';

    #[Url(except: false)]
    public bool $archived = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedActive(): void
    {
        $this->resetPage();
    }

    public function updatedArchived(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'active');
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'livewire.pagination';
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $search = trim($this->search);
        $products = Product::query()
            ->when($this->archived, fn ($q) => $q->onlyTrashed())
            ->withCount('revisions')->with('revisions')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('code', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%")))
            ->when($this->active === '1', fn ($q) => $q->where('active', true))
            ->when($this->active === '0', fn ($q) => $q->where('active', false))
            ->orderBy('code')
            ->paginate(20);

        // The grade each product is made to: that of its latest approved revision, else its latest one that is not obsolete.
        $planner = app(HeatPlanner::class);
        $grades = $products->getCollection()->mapWithKeys(function (Product $product) use ($planner) {
            $revisions = $product->revisions->sortByDesc('created_at');
            $revision = $revisions->firstWhere('status', 'APPROVED') ?? $revisions->firstWhere('status', '!=', 'OBSOLETE') ?? $revisions->first();

            return [$product->id => $revision ? ['grade' => $planner->gradeFor($revision), 'revision' => $revision->revision] : null];
        });

        // The day each product was last poured: its newest casting lot.
        $lastCast = CastingLot::whereIn('product_id', $products->pluck('id'))->whereNotNull('created_from_pour_at')
            ->groupBy('product_id')->selectRaw('product_id, max(created_from_pour_at) as last_cast')->pluck('last_cast', 'product_id')
            ->map(fn ($d) => Carbon::parse($d));

        return [
            'products' => $products,
            'grades' => $grades,
            'lastCast' => $lastCast,
            'totalProducts' => Product::count(),
            'activeProducts' => Product::where('active', true)->count(),
            'inactiveProducts' => Product::where('active', false)->count(),
            'archivedProducts' => Product::onlyTrashed()->count(),
        ];
    }
};
?>

<div>
<div class="metrics">
    <div class="metric">
        <div class="metric-label">Total products</div>
        <div class="metric-value">{{ \App\Support\Formats::number($totalProducts) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Active</div>
        <div class="metric-value">{{ \App\Support\Formats::number($activeProducts) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Inactive</div>
        <div class="metric-value">{{ \App\Support\Formats::number($inactiveProducts) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Matching results</div>
        <div class="metric-value">{{ \App\Support\Formats::number($products->total()) }}</div>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="filters" role="search">
            <div class="field">
                <label for="search">Search code or name</label>
                <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="e.g. VALVE-001" autocomplete="off">
            </div>
            <div class="field">
                <label for="active">Product status</label>
                <select id="active" wire:model.live="active">
                    <option value="">All products</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <button class="btn" type="button" wire:click="resetFilters">Reset</button>
            <span class="muted small" wire:loading>Updating…</span>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>{{ $archived ? 'Archived products' : 'Products' }}</h2>
        <span class="muted small">{{ $products->total() }} records ·
            @if ($archived)<a href="#" wire:click.prevent="$set('archived', false)">Back to current products</a>@elseif ($archivedProducts)<a href="#" wire:click.prevent="$set('archived', true)">{{ $archivedProducts }} archived</a>@else none archived @endif</span>
    </div>

    @if ($products->isEmpty())
        <div class="empty-state">
            <strong>{{ $archived ? 'No archived products' : 'No products found' }}</strong>
            <p>Create your first product to start building the production workflow.</p>
            <a class="btn btn-primary" href="{{ route('products.create') }}">Create product</a>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Metal grade</th>
                        <th>Last cast</th>
                        <th>Nominal weight</th>
                        <th>Revisions</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr wire:key="product-{{ $product->id }}">
                            <td><x-product-cell :product="$product" /></td>
                            <td>{{ $product->product_type }}</td>
                            @php $g = $grades[$product->id] ?? null; @endphp
                            <td>@if ($g && $g['grade'])<strong>{{ $g['grade']->code }}</strong><div class="muted small">rev {{ $g['revision'] }}</div>@else<span class="muted" title="Set the grade on the product's Iron grade tab">Not set</span>@endif</td>
                            <td>{{ isset($lastCast[$product->id]) ? $lastCast[$product->id]->displayDate() : '—' }}</td>
                            <td>
                                {{ $product->nominal_weight_kg !== null
                                    ? \App\Support\Formats::number((float) $product->nominal_weight_kg, 4).' kg'
                                    : '—' }}
                            </td>
                            <td>{{ $product->revisions_count }}</td>
                            <td>
                                <span class="status {{ $product->active ? 'status-completed' : 'status-cancelled' }}">
                                    {{ $product->active ? 'ACTIVE' : 'INACTIVE' }}
                                </span>
                            </td>
                            <td style="white-space:nowrap"><a href="{{ route('products.show', $product) }}">Open →</a>@if ($archived)<form method="POST" action="{{ route('products.restore', $product) }}" style="display:inline;margin-left:8px">@csrf<button class="btn btn-small" type="submit">Restore</button></form>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
