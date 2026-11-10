<?php

use App\Domains\Assets\Models\DepreciationRun;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Assets\Services\Depreciation;
use App\Support\Formats;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The fixed-asset register (design 28c) in the list layout of 13a and 24a: tabs by kind plus In progress and Retired, totals for the
 * tab, search with location and method filters, the table, and a side panel for the selected asset. Customer-owned tools are not
 * assets; they stay in the tooling register.
 */
new class extends Component
{
    use WithPagination;

    /** @var array<string, string> */
    private const TABS = ['' => 'All', 'PLANT' => 'Plant & machinery', 'TOOLS' => 'Patterns & tooling', 'BUILDING' => 'Buildings', 'VEHICLE' => 'Vehicles', 'IN_PROGRESS' => 'In progress', 'RETIRED' => 'Retired'];

    #[Url(except: '')]
    public string $tab = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $location = '';

    #[Url(except: '')]
    public string $method = '';

    public ?string $selectedId = null;

    public function showTab(string $tab = ''): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : '';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function select(string $id): void
    {
        $this->selectedId = $id;
    }

    private function inTab(FixedAsset $a, string $tab): bool
    {
        return match ($tab) {
            '' => true,
            'TOOLS' => $a->isTool(),
            'IN_PROGRESS' => $a->status === 'IN_PROGRESS',
            'RETIRED' => in_array($a->status, FixedAsset::GONE, true),
            default => $a->kind === $tab,
        };
    }

    public function with(Depreciation $depreciation): array
    {
        $all = FixedAsset::with(['assetClass', 'pattern', 'coreBox', 'equipment'])->orderBy('code')->get();
        $posted = DB::table('depreciation_run_lines')->join('depreciation_runs', 'depreciation_runs.id', '=', 'depreciation_run_lines.depreciation_run_id')
            ->where('depreciation_runs.status', DepreciationRun::POSTED)->groupBy('fixed_asset_id')
            ->selectRaw('fixed_asset_id, sum(charge) as charge, sum(coalesce(fills, 0)) as fills')->get()->keyBy('fixed_asset_id');
        $rows = $all->map(function (FixedAsset $a) use ($posted) {
            $dep = round($a->opening_depreciation + (float) ($posted[$a->id]->charge ?? 0), 2);

            return ['asset' => $a, 'depreciated' => $dep, 'book' => round($a->cost - $dep, 2), 'fills' => $a->opening_fills + (int) ($posted[$a->id]->fills ?? 0)];
        });
        $tab = array_key_exists($this->tab, self::TABS) ? $this->tab : '';
        $counts = collect(self::TABS)->map(fn ($l, $k) => $rows->filter(fn ($r) => $this->inTab($r['asset'], $k))->count())->all();
        $inTab = $rows->filter(fn ($r) => $this->inTab($r['asset'], $tab));
        $search = mb_strtolower(trim($this->search));
        $shown = $inTab->filter(fn ($r) => ($search === '' || str_contains(mb_strtolower($r['asset']->name.' '.$r['asset']->code.' '.$r['asset']->toolCode().' '.$r['asset']->location), $search))
            && ($this->location === '' || $r['asset']->location === $this->location) && ($this->method === '' || $r['asset']->method === $this->method))->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($shown->forPage($page, 25)->values(), $shown->count(), 25, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]);
        $selected = $shown->first(fn ($r) => $r['asset']->id === $this->selectedId) ?? $shown->first();
        $last = DepreciationRun::where('status', DepreciationRun::POSTED)->max('period');
        $next = $last ? Carbon::parse($last)->addMonthNoOverflow()->startOfMonth() : today()->startOfMonth();
        $current = $inTab->reject(fn ($r) => in_array($r['asset']->status, FixedAsset::GONE, true));
        $progress = $inTab->filter(fn ($r) => $r['asset']->status === 'IN_PROGRESS');

        return [
            'tabs' => self::TABS, 'tabKey' => $tab, 'counts' => $counts, 'rows' => $paged, 'shownCount' => $shown->count(), 'shownBook' => round($shown->sum('book'), 2),
            'kpi' => [
                'cost' => round($current->sum(fn ($r) => $r['asset']->cost), 2), 'count' => $current->count(), 'book' => round($current->sum('book'), 2),
                'progress' => round($progress->sum(fn ($r) => $r['asset']->cost), 2), 'progressCount' => $progress->count(),
                'noTax' => $current->filter(fn ($r) => ! $r['asset']->tax_class && ! $r['asset']->assetClass?->tax_class && $r['asset']->method !== 'NONE')->count(),
            ],
            'bookTo' => $last ? Carbon::parse($last)->endOfMonth() : null,
            'locations' => $all->pluck('location')->filter()->unique()->sort()->values(), 'methods' => FixedAsset::METHODS,
            'customerTools' => $tab === 'TOOLS' ? DB::table('patterns')->whereNull('deleted_at')->where('owner_type', '!=', 'FOUNDRY')->count() + DB::table('core_boxes')->whereNull('deleted_at')->where('owner_type', '!=', 'FOUNDRY')->count() : 0,
            'panel' => $selected ? $selected + ['pending' => $selected['asset']->status === 'IN_USE' && $next->lte(today()) ? $depreciation->forMonth($next, [$selected['asset']->id])->first() : null, 'next' => $next] : null,
        ];
    }
};
?>
@php
    $num = fn ($v) => Formats::number((float) $v, 0);
    $statusPill = fn (string $s) => match ($s) { 'IN_USE' => 'fa-ok', 'IN_PROGRESS' => 'fa-info', default => 'fa-grey' };
    $methodText = function (array $r) use ($num) {
        $a = $r['asset'];
        if ($a->status === 'IN_PROGRESS') { return 'starts when in use'; }
        if ($a->method === 'USAGE') { return 'Usage · '.($r['fills'] >= (int) $a->life_fills && $a->life_fills ? 'life reached' : $num($r['fills']).' of '.$num($a->life_fills).' fills'); }
        return $a->methodLabel();
    };
@endphp
<div class="fa-card">
    <div class="fa-bar">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <strong style="font-weight:600">Fixed assets</strong>
            <nav class="fa-tabs" role="tablist">
                @foreach ($tabs as $key => $label)
                    <a href="#" role="tab" aria-selected="{{ $tabKey === $key ? 'true' : 'false' }}" wire:click.prevent="showTab('{{ $key }}')" @class(['on' => $tabKey === $key])>{{ $label }} <span class="n">{{ $counts[$key] }}</span></a>
                @endforeach
            </nav>
        </div>
        <div style="display:flex;gap:8px">
            <a class="fa-btn" href="{{ route('assets.depreciation') }}">Depreciation run</a>
            <a class="fa-btn primary" href="{{ route('assets.create') }}">New asset</a>
        </div>
    </div>
    <div class="fa-grid">
        <div class="fa-main">
            <div class="fa-kpis">
                <div class="fa-kpi"><div class="lab">{{ $tabs[$tabKey] === 'All' ? 'Cost' : $tabs[$tabKey].', cost' }}</div><div class="v">Rs {{ $num($kpi['cost']) }}</div><div class="s">{{ $kpi['count'] }} {{ $kpi['count'] === 1 ? 'asset' : 'assets' }} we own</div></div>
                <div class="fa-kpi"><div class="lab">Book value</div><div class="v">Rs {{ $num($kpi['book']) }}</div><div class="s">{{ $bookTo ? 'after depreciation to '.$bookTo->format('j M') : 'no run posted yet' }}</div></div>
                <div class="fa-kpi"><div class="lab">In progress</div><div class="v">Rs {{ $num($kpi['progress']) }}</div><div class="s">{{ $kpi['progressCount'] }} not yet in use</div></div>
                <div class="fa-kpi"><div class="lab">Needs a tax class</div><div class="v">{{ $kpi['noTax'] }}</div><div class="s">set before year-end</div></div>
            </div>
            <div class="fa-filters">
                <label class="fa-search">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#55585d" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search asset, tool or location" autocomplete="off" aria-label="Search asset, tool or location">
                </label>
                <label class="fa-chip"><span class="k">Location</span><b>{{ $location ?: 'All' }}</b><span aria-hidden="true">▾</span>
                    <select wire:model.live="location" aria-label="Location"><option value="">All</option>@foreach ($locations as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach</select></label>
                <label class="fa-chip"><span class="k">Method</span><b>{{ $methods[$method] ?? 'All' }}</b><span aria-hidden="true">▾</span>
                    <select wire:model.live="method" aria-label="Method"><option value="">All</option>@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            </div>
            @if ($rows->isEmpty())
                <p class="fa-sub">{{ $counts[''] === 0 ? 'No asset yet. Add the plant, buildings, vehicles and tools the foundry owns with New asset.' : 'No asset matches these filters.' }}</p>
            @else
            <div class="fa-table"><table>
                <thead><tr><th>Asset</th><th>Kind</th><th>In use since</th><th class="r">Cost</th><th class="r">Depreciated</th><th class="r">Book value</th><th>Method</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    @php $a = $r['asset']; @endphp
                    <tr wire:key="fa-{{ $a->id }}" wire:click="select('{{ $a->id }}')" @class(['pick', 'sel' => $panel && $panel['asset']->id === $a->id])>
                        <td><a href="{{ route('assets.show', $a) }}" wire:click.stop>{{ $a->name }}</a><div class="fa-sub">{{ $a->code }}@if ($a->toolCode()) · {{ $a->isTool() ? 'tool ' : '' }}{{ $a->toolCode() }}@endif</div></td>
                        <td>{{ \App\Domains\Assets\Models\FixedAsset::KINDS[$a->kind] ?? $a->kind }}</td>
                        <td class="nw">{{ $a->in_use_on?->displayDate() ?? '—' }}</td>
                        <td class="r nw">{{ $num($a->cost) }}</td>
                        <td class="r nw">{{ $a->status === 'IN_PROGRESS' ? '—' : $num($r['depreciated']) }}</td>
                        <td class="r nw">{{ $a->status === 'IN_PROGRESS' ? '—' : $num($r['book']) }}</td>
                        <td class="fa-sub">{{ $methodText($r) }}</td>
                        <td><span class="fa-pill {{ $statusPill($a->status) }}">{{ \App\Domains\Assets\Models\FixedAsset::STATUSES[$a->status] }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="fa-sub">{{ $rows->count() }} of {{ $shownCount }} shown · book value Rs {{ $num($shownBook) }}@if ($customerTools) · Customer-owned tools ({{ $customerTools }}) are not assets; they stay in the tooling register with owner and custody.@endif</div>
            {{ $rows->links() }}
            @endif
        </div>
        <aside class="fa-side">
            @if ($panel)
                @php $a = $panel['asset']; $p = $panel['pending']; @endphp
                <div style="display:flex;justify-content:space-between;align-items:center"><strong>{{ $a->code }}</strong><span class="fa-pill {{ $statusPill($a->status) }}">{{ \App\Domains\Assets\Models\FixedAsset::STATUSES[$a->status] }}</span></div>
                <div>{{ $a->name }}@if ($a->toolCode()) · {{ $a->toolCode() }}@endif @if ($a->location)· {{ $a->location }}@endif</div>
                <div class="fa-two">
                    <div><div class="fa-lab">Cost</div><div class="fa-big">Rs {{ $num($a->cost) }}</div></div>
                    <div><div class="fa-lab">Book value</div><div class="fa-big">Rs {{ $num($panel['book']) }}</div></div>
                </div>
                <div class="fa-two">
                    <div><div class="fa-lab">Rate</div><div>
                        @if ($a->method === 'USAGE' && $a->life_fills) Rs {{ $num(($a->cost - $a->residual_value) / $a->life_fills) }} per fill
                        @elseif ($a->method === 'STRAIGHT_LINE' && $a->life_years) Rs {{ $num(($a->cost - $a->residual_value) / ($a->life_years * 12)) }} a month
                        @elseif ($a->method === 'REDUCING_BALANCE') {{ rtrim(rtrim(number_format((float) $a->rate_percent, 1), '0'), '.') }}% a year on book value
                        @else — @endif</div></div>
                    <div><div class="fa-lab">{{ $panel['next']->format('F') }}</div><div>
                        @if ($p) @if ($p['fills'] !== null){{ $num($p['fills']) }} fills · @endif Rs {{ $num($p['charge']) }} @else — @endif</div></div>
                </div>
                @if ($a->method === 'USAGE')
                    <div><div class="fa-lab">Life used</div><div>{{ $num($panel['fills']) }} of {{ $num($a->life_fills) }} fills · fills come from {{ $a->kind === 'CORE_BOX' ? 'core lots' : 'moulding runs' }}</div></div>
                @endif
                @if ($a->events()->exists())
                    @php $first = $a->events()->first(); @endphp
                    <div><div class="fa-lab">Came from</div><div>{{ $first->note ?? ucfirst(\App\Domains\Assets\Models\AssetEvent::KINDS[$first->kind] ?? '') }}</div></div>
                @endif
                <div><a class="fa-btn" href="{{ route('assets.show', $a) }}">Open asset</a></div>
            @else
                <p class="fa-sub">Pick an asset to see its cost, book value and this month's charge.</p>
            @endif
        </aside>
    </div>
</div>
