<?php

use App\Domains\Assets\Models\DepreciationRun;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Assets\Services\Depreciation;
use App\Support\Formats;
use App\Support\ListDetails;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The fixed-asset register (design 28c): tabs by kind plus In progress and Sold or retired, search, a Filters menu (location,
 * method), quick flags (near end of life, no tax class, idle 6+ months), sortable columns, and a Details toggle. With Details on, a
 * row click shows the asset in the side panel and the list stays put; with it off, a row click opens the asset page and the freed
 * width adds asset no., last used and cost. The toggle is kept for the session so other lists can follow it. Customer-owned tools
 * are not assets; they stay in the tooling register.
 */
new class extends Component
{
    use ListDetails;
    use WithPagination;

    /** @var array<string, string> */
    private const TABS = ['' => 'All', 'PLANT' => 'Plant & machinery', 'TOOLS' => 'Patterns & tooling', 'BUILDING' => 'Buildings', 'VEHICLE' => 'Vehicles', 'IN_PROGRESS' => 'In progress', 'RETIRED' => 'Sold or retired'];

    /** @var array<string, string> */
    private const FLAGS = ['END' => 'near end of life', 'TAX' => 'no tax class', 'IDLE' => 'idle 6+ months'];

    /** @var list<string> */
    private const SORTS = ['name', 'code', 'tool', 'where', 'life', 'last', 'cost', 'book', 'status'];

    #[Url(except: '')]
    public string $tab = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $location = '';

    #[Url(except: '')]
    public string $method = '';

    #[Url(except: '')]
    public string $flag = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    #[Url(except: 'asc')]
    public string $dir = 'asc';

    #[Url(except: 25)]
    public int $perPage = 25;

    public ?string $selectedId = null;

    public function showTab(string $tab = ''): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : '';
        $this->resetPage();
    }

    public function showFlag(string $flag = ''): void
    {
        $this->flag = $this->flag === $flag || ! isset(self::FLAGS[$flag]) ? '' : $flag;
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTS, true)) {
            return;
        }
        $this->dir = $this->sort === $column && $this->dir === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function showRows(int $count): void
    {
        $this->perPage = in_array($count, [25, 50, 100], true) ? $count : 25;
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'location', 'method'], true)) {
            $this->resetPage();
        }
    }

    public function select(string $id): void
    {
        $this->selectedId = $id;
    }

    public function export(): StreamedResponse
    {
        $rows = $this->shown($this->rows());

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Asset no.', 'Asset', 'Kind', 'Tool', 'Where it is', 'In use since', 'Life used %', 'Last used', 'Cost', 'Depreciated', 'Book value', 'Method', 'Status']);
            foreach ($rows as $r) {
                $a = $r['asset'];
                fputcsv($out, [$a->code, $a->name, FixedAsset::KINDS[$a->kind] ?? $a->kind, $a->toolCode(), $r['where'], $a->in_use_on?->toDateString(), $r['life'] === null ? '' : round($r['life']),
                    $r['last']?->toDateString(), $a->cost, $r['depreciated'], $r['book'], FixedAsset::METHODS[$a->method] ?? $a->method, $r['status']]);
            }
            fclose($out);
        }, 'fixed-assets-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Every asset with what the list shows for it.
     *
     * @return Collection<int, array{asset: FixedAsset, depreciated: float, book: float, fills: int, life: float|null, last: Carbon|null, lastRef: string|null, where: string|null, idle: bool, end: bool, noTax: bool, status: string}>
     */
    private function rows(): Collection
    {
        $all = FixedAsset::with(['assetClass', 'pattern', 'coreBox', 'equipment', 'disposal.invoice'])->orderBy('code')->get();
        $posted = DB::table('depreciation_run_lines')->join('depreciation_runs', 'depreciation_runs.id', '=', 'depreciation_run_lines.depreciation_run_id')
            ->where('depreciation_runs.status', DepreciationRun::POSTED)->groupBy('fixed_asset_id')
            ->selectRaw('fixed_asset_id, sum(charge) as charge, sum(coalesce(fills, 0)) as fills')->get()->keyBy('fixed_asset_id');
        $lastPattern = DB::table('moulding_runs')->join('pattern_revisions', 'pattern_revisions.id', '=', 'moulding_runs.pattern_revision_id')
            ->whereIn('pattern_revisions.pattern_id', $all->pluck('pattern_id')->filter()->all())->where('moulding_runs.status', '!=', 'CANCELLED')->where('moulding_runs.moulds_made_qty', '>', 0)
            ->orderBy('moulding_runs.run_date')->get(['pattern_revisions.pattern_id', 'moulding_runs.run_date', 'moulding_runs.run_number'])->keyBy('pattern_id');
        $lastCore = DB::table('core_lots')->whereIn('core_box_id', $all->pluck('core_box_id')->filter()->all())->where('quantity_made', '>', 0)
            ->orderBy('made_on')->get(['core_box_id', 'made_on', 'lot_number'])->keyBy('core_box_id');
        $idleFrom = today()->subMonthsNoOverflow(6);

        return $all->map(function (FixedAsset $a) use ($posted, $lastPattern, $lastCore, $idleFrom) {
            $dep = round($a->opening_depreciation + (float) ($posted[$a->id]->charge ?? 0) + (float) ($a->disposal?->charge ?? 0), 2);
            $fills = $a->opening_fills + (int) ($posted[$a->id]->fills ?? 0);
            $life = match (true) {
                $a->status === 'IN_PROGRESS', $a->method === 'NONE' => null,
                $a->method === 'USAGE' => $a->life_fills > 0 ? min(100.0, $fills * 100 / $a->life_fills) : null,
                default => $a->cost - $a->residual_value > 0 ? min(100.0, $dep * 100 / ($a->cost - $a->residual_value)) : null,
            };
            $used = $a->pattern_id ? ($lastPattern[$a->pattern_id] ?? null) : ($a->core_box_id ? ($lastCore[$a->core_box_id] ?? null) : null);
            $last = $used ? Carbon::parse($used->run_date ?? $used->made_on) : null;
            $inUse = $a->status === 'IN_USE';
            $idle = $inUse && $a->isTool() && ($last ? $last->lt($idleFrom) : ($a->in_use_on && $a->in_use_on->lt($idleFrom)));

            return [
                'asset' => $a, 'depreciated' => $dep, 'book' => round($a->cost - $dep, 2), 'fills' => $fills, 'life' => $life, 'last' => $last,
                'lastRef' => $used->run_number ?? $used->lot_number ?? null, 'where' => $a->location ?: ($a->pattern?->custody_location ?? $a->coreBox?->custody_location),
                'idle' => $idle, 'end' => $inUse && $life !== null && $life >= 90,
                'noTax' => ! in_array($a->status, FixedAsset::GONE, true) && $a->method !== 'NONE' && ! $a->tax_class && ! $a->assetClass?->tax_class,
                'status' => $idle ? 'Idle' : FixedAsset::STATUSES[$a->status],
            ];
        });
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

    /**
     * The rows of the tab that pass the search, filters and flag, in the chosen order.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function shown(Collection $rows, bool $withFlag = true): Collection
    {
        $tab = array_key_exists($this->tab, self::TABS) ? $this->tab : '';
        $search = mb_strtolower(trim($this->search));
        $shown = $rows->filter(fn ($r) => $this->inTab($r['asset'], $tab)
            && ($search === '' || str_contains(mb_strtolower($r['asset']->name.' '.$r['asset']->code.' '.$r['asset']->toolCode().' '.$r['where']), $search))
            && ($this->location === '' || $r['where'] === $this->location) && ($this->method === '' || $r['asset']->method === $this->method)
            && (! $withFlag || match ($this->flag) { 'END' => $r['end'], 'TAX' => $r['noTax'], 'IDLE' => $r['idle'], default => true }));
        $key = match ($this->sort) {
            'code' => fn ($r) => $r['asset']->code,
            'tool' => fn ($r) => (string) $r['asset']->toolCode(),
            'where' => fn ($r) => mb_strtolower((string) $r['where']),
            'life' => fn ($r) => $r['life'] ?? -1,
            'last' => fn ($r) => $r['last']?->timestamp ?? 0,
            'cost' => fn ($r) => $r['asset']->cost,
            'book' => fn ($r) => $r['book'],
            'status' => fn ($r) => $r['status'],
            default => fn ($r) => mb_strtolower($r['asset']->name),
        };

        return $shown->sortBy($key, SORT_REGULAR, $this->dir === 'desc')->values();
    }

    /**
     * What the side panel adds for one asset: fills a month and when its life runs out, where it came from, and what happened lately.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function panel(array $row, Depreciation $depreciation): array
    {
        $a = $row['asset'];
        [$from, $to] = [today()->subMonthsNoOverflow(6)->startOfMonth(), today()];
        $perMonth = null;
        $lastsTo = null;
        if ($a->method === 'USAGE' && $a->status === 'IN_USE') {
            $found = $a->pattern_id ? ($depreciation->patternFills([$a->pattern_id], $from, $to)[$a->pattern_id] ?? [])
                : ($a->core_box_id ? ($depreciation->coreBoxFills([$a->core_box_id], $from, $to)[$a->core_box_id] ?? []) : []);
            $perMonth = (int) round(array_sum(array_column($found, 'fills')) / 6);
            $left = max(0, (int) $a->life_fills - $row['fills']);
            $lastsTo = $perMonth > 0 ? today()->addMonthsNoOverflow((int) ceil($left / $perMonth)) : null;
        } elseif (in_array($a->method, ['STRAIGHT_LINE'], true) && $a->in_use_on && $a->life_years) {
            $lastsTo = $a->in_use_on->copy()->addMonthsNoOverflow((int) round($a->life_years * 12));
        }
        $development = $a->pattern_development_id ? DB::table('pattern_developments')->where('id', $a->pattern_development_id)->first(['id', 'code']) : null;
        $events = $a->events()->latest('happened_on')->latest('created_at')->limit(3)->get()
            ->map(fn ($e) => ['what' => ucfirst(\App\Domains\Assets\Models\AssetEvent::KINDS[$e->kind] ?? strtolower($e->kind)).($e->note ? ' · '.\Illuminate\Support\Str::limit($e->note, 60) : ''), 'on' => $e->happened_on]);
        $runs = $a->runLines()->with('run')->whereHas('run', fn ($q) => $q->where('status', DepreciationRun::POSTED))->get()->sortByDesc(fn ($l) => $l->run->period)->take(3)
            ->map(fn ($l) => ['what' => $l->run->period->format('M').' depreciation'.($l->fills !== null ? ' · '.Formats::number((float) $l->fills, 0).' fills' : ' · Rs '.Formats::number((float) $l->charge, 0)), 'on' => $l->run->periodEnd()]);

        return $row + ['perMonth' => $perMonth, 'lastsTo' => $lastsTo, 'development' => $development,
            'recent' => $events->concat($runs)->sortByDesc(fn ($x) => $x['on']->timestamp)->take(3)->values()];
    }

    public function with(Depreciation $depreciation): array
    {
        $rows = $this->rows();
        $tab = array_key_exists($this->tab, self::TABS) ? $this->tab : '';
        $counts = collect(self::TABS)->map(fn ($l, $k) => $rows->filter(fn ($r) => $this->inTab($r['asset'], $k))->count())->all();
        $unflagged = $this->shown($rows, false);
        $flags = collect(self::FLAGS)->map(fn ($l, $k) => $unflagged->filter(fn ($r) => match ($k) { 'END' => $r['end'], 'TAX' => $r['noTax'], default => $r['idle'] })->count())->all();
        $shown = $this->shown($rows);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($shown->forPage($page, $this->perPage)->values(), $shown->count(), $this->perPage, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]);
        $selected = $this->details ? ($shown->first(fn ($r) => $r['asset']->id === $this->selectedId) ?? $paged->first()) : null;
        $last = DepreciationRun::where('status', DepreciationRun::POSTED)->max('period');
        $next = $last ? Carbon::parse($last)->addMonthNoOverflow()->startOfMonth() : today()->startOfMonth()->subMonthNoOverflow();

        return [
            'tabs' => self::TABS, 'tabKey' => $tab, 'counts' => $counts, 'flags' => self::FLAGS, 'flagCounts' => $flags, 'rows' => $paged, 'shownCount' => $shown->count(),
            'shownCost' => round($shown->sum(fn ($r) => $r['asset']->cost), 2), 'shownBook' => round($shown->sum('book'), 2),
            'runDue' => $next->copy()->endOfMonth()->lt(today()) && $rows->contains(fn ($r) => $r['asset']->status === 'IN_USE'), 'lastRun' => $last ? Carbon::parse($last) : null,
            'locations' => $rows->pluck('where')->filter()->unique()->sort()->values(), 'methods' => FixedAsset::METHODS,
            'customerTools' => $tab === 'TOOLS' ? DB::table('patterns')->whereNull('deleted_at')->where('owner_type', '!=', 'FOUNDRY')->count() + DB::table('core_boxes')->whereNull('deleted_at')->where('owner_type', '!=', 'FOUNDRY')->count() : 0,
            'panel' => $selected ? $this->panel($selected, $depreciation) : null,
        ];
    }
};
?>
@php
    $num = fn ($v) => Formats::number((float) $v, 0);
    $pill = fn (array $r) => match (true) { $r['idle'] => 'fa-warn', $r['asset']->status === 'IN_USE' => 'fa-ok', $r['asset']->status === 'IN_PROGRESS' => 'fa-info', default => 'fa-grey' };
    $lastText = fn (?\Illuminate\Support\Carbon $d) => $d ? ($d->isToday() ? 'Today' : ($d->year === today()->year ? $d->format('j M') : $d->format('j M Y'))) : '—';
    $head = function (string $key, string $label, string $class = '') use ($sort, $dir) {
        $on = $sort === $key;
        $arrow = $on ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
        $aria = $on ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';

        return '<th class="'.$class.'" aria-sort="'.$aria.'"><button type="button" class="fr-sort'.($on ? ' on' : '').'" wire:click="sortBy(\''.$key.'\')">'.e($label).'<span class="fr-arrow">'.$arrow.'</span></button></th>';
    };
    $lifeBar = function (array $r) {
        if ($r['life'] === null) { return '<span class="fa-sub">—</span>'; }
        $p = (int) round($r['life']);
        $warn = $p >= 90;

        return '<span class="fr-life"><span class="fr-track"><span style="width:'.$p.'%;background:'.($warn ? '#c58a1a' : '#1d7048').'"></span></span><span class="fr-pct'.($warn ? ' warn' : '').'">'.$p.'%</span></span>';
    };
@endphp
<div class="fa-card fr-card">
    <div class="fr-head">
        <h1 class="fr-title">Fixed assets</h1>
        <div class="fr-actions">
            <details class="fr-menu">
                <summary class="fa-btn">Reports <span aria-hidden="true" class="fa-sub">▾</span></summary>
                <div class="fr-pop" role="menu">
                    @if ($lastRun)<a role="menuitem" href="{{ route('assets.depreciation.sheet', ['period' => $lastRun->format('Y-m')]) }}">Depreciation sheet, {{ $lastRun->format('F Y') }}</a>@endif
                    <a role="menuitem" href="{{ route('assets.depreciation') }}">Depreciation runs</a>
                    <button type="button" role="menuitem" wire:click="export">Register, as a sheet</button>
                </div>
            </details>
            <a class="fa-btn" href="{{ route('assets.depreciation') }}">@if ($runDue)<span class="fr-dot" title="A month is ready to run" aria-hidden="true"></span>@endif Depreciation run</a>
            <a class="fa-btn primary" href="{{ route('assets.create') }}">New asset</a>
        </div>
    </div>
    <nav class="fr-tabs" aria-label="Asset kinds">
        @foreach ($tabs as $key => $label)
            <a href="#" wire:click.prevent="showTab('{{ $key }}')" @class(['on' => $tabKey === $key]) @if ($tabKey === $key) aria-current="page" @endif>{{ $label }}<span class="n">{{ $counts[$key] }}</span></a>
        @endforeach
    </nav>
    <div class="fr-tools">
        <label class="fa-search fr-search" style="width:{{ $details ? 240 : 320 }}px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#55585d" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Name, asset or tool no." autocomplete="off" aria-label="Search by name, asset or tool no.">
        </label>
        <details class="fr-menu">
            <summary class="fr-tool @if ($location || $method) on @endif"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8L3 5z"/></svg>Filters @if ($location || $method) <b>{{ ($location ? 1 : 0) + ($method ? 1 : 0) }}</b>@endif</summary>
            <div class="fr-pop fr-filters">
                <label>Where it is<select wire:model.live="location"><option value="">All</option>@foreach ($locations as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach</select></label>
                <label>Method<select wire:model.live="method"><option value="">All</option>@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            </div>
        </details>
        <span class="fr-sep" aria-hidden="true"></span>
        @foreach ($flags as $key => $label)
            @if ($flagCounts[$key] > 0 || $flag === $key)
                <button type="button" wire:click="showFlag('{{ $key }}')" @class(['fr-flag', 'on' => $flag === $key]) aria-pressed="{{ $flag === $key ? 'true' : 'false' }}"><b>{{ $flagCounts[$key] }}</b>{{ $label }}</button>
            @endif
        @endforeach
        <span class="fr-right">
            <button type="button" class="fr-tool" wire:click="export"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v12m0 0-4-4m4 4 4-4M5 20h14"/></svg>Export</button>
            <span class="fr-sep" aria-hidden="true"></span>
            <button type="button" wire:click="toggleDetails" @class(['fr-tool', 'fr-details', 'on' => $details]) aria-pressed="{{ $details ? 'true' : 'false' }}" title="{{ $details ? 'Hide' : 'Show' }} detail panel">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="14.5" y="4" width="6.5" height="16" rx="1.5" fill="currentColor" opacity="0.14" stroke="none"/><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="14.5" y1="4" x2="14.5" y2="20"/></svg>Details</button>
        </span>
    </div>

    <div @class(['fr-body', 'with-panel' => $details])>
        <div class="fr-list" x-data
             @if ($details) x-on:keydown.arrow-down.prevent="($event.target.closest('tr')?.nextElementSibling)?.focus()" x-on:keydown.arrow-up.prevent="($event.target.closest('tr')?.previousElementSibling)?.focus()" @endif>
            @if ($rows->isEmpty())
                <p class="fa-sub fr-empty">{{ $counts[''] === 0 && $tabKey === '' ? 'No asset yet. Add the plant, buildings, vehicles and tools the foundry owns with New asset.' : 'No asset matches.' }}</p>
            @else
            <div class="fr-scroll"><table class="fr-table">
                <thead><tr>
                    {!! $head('name', 'Asset') !!}
                    @unless ($details){!! $head('code', 'Asset no.') !!}@endunless
                    {!! $head('tool', 'Tool') !!}
                    {!! $head('where', 'Where it is') !!}
                    {!! $head('life', 'Life used') !!}
                    @unless ($details){!! $head('last', 'Last used') !!}{!! $head('cost', 'Cost', 'r') !!}@endunless
                    {!! $head('book', 'Book value', 'r') !!}
                    {!! $head('status', 'Status') !!}
                    @unless ($details)<th><span class="sr-only">Actions</span></th>@endunless
                </tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    @php $a = $r['asset']; $show = route('assets.show', $a); @endphp
                    <tr wire:key="fa-{{ $a->id }}-{{ $details ? 'p' : 'f' }}" tabindex="0" @class(['sel' => $panel && $panel['asset']->id === $a->id])
                        @if ($details) wire:click="select('{{ $a->id }}')" x-on:focus="$wire.select('{{ $a->id }}')" x-on:dblclick="window.location = '{{ $show }}'" x-on:keydown.enter="window.location = '{{ $show }}'"
                        @else x-on:click="if (! $event.target.closest('a,button,summary,details')) window.location = '{{ $show }}'" x-on:keydown.enter="window.location = '{{ $show }}'" @endif>
                        <td class="fr-name">
                            <a href="{{ $show }}" @if ($details) x-on:click.prevent.stop="$wire.select('{{ $a->id }}')" @endif>{{ $a->name }}</a>
                            @if ($details)<span class="fr-code">{{ $a->code }}</span>@endif
                            @if ($r['end'])<span class="fr-warn">! Life nearly used</span>@elseif ($r['noTax'])<span class="fr-warn">! No tax class</span>@endif
                        </td>
                        @unless ($details)<td class="fr-dim nw">{{ $a->code }}</td>@endunless
                        <td class="fr-dim nw">{{ $a->toolCode() ?? '—' }}</td>
                        <td class="fr-dim fr-where">{{ $r['where'] ?: '—' }}</td>
                        <td>{!! $lifeBar($r) !!}</td>
                        @unless ($details)<td class="fr-dim nw">{{ $a->isTool() ? $lastText($r['last']) : '—' }}</td><td class="r nw">{{ $num($a->cost) }}</td>@endunless
                        <td class="r nw">{{ $a->status === 'IN_PROGRESS' ? '—' : $num($r['book']) }}</td>
                        <td><span class="fa-pill {{ $pill($r) }}">{{ $r['status'] }}</span></td>
                        @unless ($details)
                            <td class="fr-act"><details class="fr-menu right"><summary class="fr-more" aria-label="Actions for {{ $a->code }}">⋯</summary>
                                <div class="fr-pop" role="menu"><a role="menuitem" href="{{ $show }}">Open asset</a><a role="menuitem" href="{{ route('assets.edit', $a) }}">Edit or move</a>
                                    @if ($a->status === 'IN_USE')<a role="menuitem" href="{{ $show }}#dispose">Sell or retire</a>@endif</div></details></td>
                        @endunless
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @endif
        </div>

        @if ($details)
        <aside class="fr-panel" aria-label="Asset details">
            @if ($panel)
                @php $a = $panel['asset']; $p = $panel; @endphp
                <div class="fr-ph">
                    <div style="flex:1;min-width:0"><div class="fr-pt">{{ $a->name }}</div>
                        <div class="fa-sub" style="margin-top:2px">{{ $a->code }}@if ($a->toolCode()) · {{ $a->isTool() ? 'tool ' : '' }}{{ $a->toolCode() }}@endif · {{ \App\Domains\Assets\Models\FixedAsset::KINDS[$a->kind] ?? $a->kind }}</div></div>
                    <button type="button" class="fr-hide" wire:click="toggleDetails" title="Hide panel" aria-label="Hide panel"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 6l6 6-6 6M5 6l6 6-6 6"/></svg></button>
                </div>
                <div class="fr-pbtns">
                    <a class="fa-btn primary" href="{{ route('assets.show', $a) }}">Open asset</a>
                    <a class="fa-btn" href="{{ route('assets.edit', $a) }}">Move</a>
                    <details class="fr-menu right"><summary class="fa-btn fr-more" aria-label="More actions">⋯</summary>
                        <div class="fr-pop" role="menu"><a role="menuitem" href="{{ route('assets.edit', $a) }}">Edit</a>@if ($a->status === 'IN_USE')<a role="menuitem" href="{{ route('assets.show', $a) }}#dispose">Sell or retire</a>@endif</div></details>
                </div>
                @if ($p['noTax'])
                    <div class="fr-alert"><span><b>No tax class.</b> Needed before the year-end tax computation.</span><a class="fa-btn" href="{{ route('assets.edit', $a) }}">Set</a></div>
                @elseif ($p['end'])
                    <div class="fr-alert"><span><b>Life nearly used.</b> Plan a replacement or a rebuild.</span></div>
                @endif
                @if ($p['life'] !== null)
                    <div>
                        <div class="fa-sec">Life used</div>
                        <div class="fr-track big"><span style="width:{{ (int) round($p['life']) }}%;background:{{ $p['life'] >= 90 ? '#c58a1a' : '#1d7048' }}"></span></div>
                        <div class="fa-sub">
                            @if ($a->method === 'USAGE'){{ $num($p['fills']) }} of {{ $num($a->life_fills) }} fills @if ($p['perMonth']) · about {{ $num($p['perMonth']) }} a month @endif
                            @else{{ (int) round($p['life']) }}% depreciated @endif
                            @if ($p['lastsTo']) · lasts to {{ $p['lastsTo']->format('M Y') }}@endif
                        </div>
                    </div>
                @endif
                <div>
                    <div class="fr-kv"><span>Cost</span><span>Rs {{ $num($a->cost) }}</span></div>
                    @if ($a->status !== 'IN_PROGRESS')
                        <div class="fr-kv"><span>Depreciated</span><span>Rs {{ $num($p['depreciated']) }}</span></div>
                        <div class="fr-kv"><span>Book value</span><span>Rs {{ $num($p['book']) }}</span></div>
                    @endif
                    <div class="fr-kv"><span>Method</span><span>@if ($a->method === 'USAGE' && $a->life_fills)Usage · Rs {{ $num(($a->cost - $a->residual_value) / $a->life_fills) }} per fill @else{{ $a->methodLabel() }}@endif</span></div>
                    <div class="fr-kv"><span>In use since</span><span>{{ $a->in_use_on?->displayDate() ?? 'Not yet' }}</span></div>
                    <div class="fr-kv"><span>Where it is</span><span>{{ $p['where'] ?: '—' }}</span></div>
                    @if ($a->isTool())<div class="fr-kv"><span>Last used</span><span>{{ $lastText($p['last']) }}@if ($p['lastRef']) · {{ $p['lastRef'] }}@endif</span></div>@endif
                    @if ($a->disposal)
                        <div class="fr-kv"><span>{{ \App\Domains\Assets\Models\FixedAsset::STATUSES[$a->status] }}</span><span>{{ $a->disposal->disposed_on->displayDate() }}@if ($a->disposal->invoice) · <a href="{{ route('invoices.show', $a->disposal->invoice) }}">{{ $a->disposal->invoice->invoice_number }}</a>@endif</span></div>
                        <div class="fr-kv"><span>{{ $a->disposal->gain >= 0 ? 'Gain' : 'Loss' }}</span><span>Rs {{ $num(abs($a->disposal->gain)) }}</span></div>
                    @endif
                    @if ($p['development'])<div class="fr-kv"><span>Came from</span><span>@if (Route::has('developments.show'))<a href="{{ route('developments.show', $p['development']->id) }}">{{ $p['development']->code }}</a>@else{{ $p['development']->code }}@endif</span></div>@endif
                </div>
                @if ($p['recent']->isNotEmpty())
                    <div>
                        <div class="fa-sec">Recent</div>
                        @foreach ($p['recent'] as $x)
                            <div class="fr-recent"><span>{{ $x['what'] }}</span><span class="fa-sub">{{ $x['on']->format('j M') }}</span></div>
                        @endforeach
                    </div>
                @endif
            @else
                <p class="fa-sub">Pick an asset to see it here.</p>
            @endif
        </aside>
        @endif
    </div>

    <div class="fr-foot">
        <span class="fa-sub">{{ $rows->count() < $shownCount ? $rows->firstItem().'–'.$rows->lastItem().' of '.$shownCount : $shownCount.' of '.$shownCount }}</span>
        <span>Cost <b>Rs {{ $num($shownCost) }}</b></span>
        <span>Book value <b>Rs {{ $num($shownBook) }}</b></span>
        @if ($customerTools)<span class="fa-sub">Customer-owned tools ({{ $customerTools }}) are not assets · @if (Route::has('patterns.index'))<a href="{{ route('patterns.index') }}">Tooling register</a>@else tooling register @endif</span>@endif
        <span class="fr-rows">
            @if ($rows->hasPages())<span class="fr-pages">{{ $rows->links() }}</span>@endif
            Rows @foreach ([25, 50, 100] as $n)<button type="button" wire:click="showRows({{ $n }})" @class(['on' => $perPage === $n]) aria-pressed="{{ $perPage === $n ? 'true' : 'false' }}">{{ $n }}</button>@endforeach
        </span>
    </div>
</div>
