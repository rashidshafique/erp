<?php

use App\Domains\Tooling\Services\CoreLotService;
use App\Models\CoreDefinition;
use App\Support\Formats;
use App\Support\ListDetails;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The cores list (design 29a): status tabs, search, type and process filters, and a Details toggle. With Details on, a row click
 * shows the core in the side panel with the boxes that make it and the products that need it; with it off, a row click opens the core.
 */
new class extends Component
{
    use ListDetails;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $process = '';

    #[Url(except: false)]
    public bool $archived = false;

    /** The core whose summary shows in the side panel. */
    public ?string $focused = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'type', 'process', 'archived'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'type', 'process');
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'livewire.pagination';
    }

    public function showStatus(string $status): void
    {
        $this->archived = false;
        $this->status = $status;
        $this->resetPage();
    }

    public function focus(string $coreId): void
    {
        $this->focused = $coreId;
    }

    /** Downloads every core that matches the filters, not only this page, as CSV. */
    public function export(): StreamedResponse
    {
        $cores = $this->filtered()->with('cavities.coreBox')->orderBy('code')->get();
        $onHand = app(CoreLotService::class)->onHand();

        return response()->streamDownload(function () use ($cores, $onHand) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Name', 'Mark', 'Type', 'Process', 'Weight (kg)', 'Made by', 'On hand', 'Status']);
            foreach ($cores as $core) {
                fputcsv($out, [
                    $core->code, $core->name, $core->mark_code, CoreDefinition::TYPES[$core->core_type] ?? $core->core_type, $core->process_type,
                    $core->nominal_weight_kg !== null ? (float) $core->nominal_weight_kg : '', $this->boxesOf($core)->pluck('code')->implode(', '),
                    $onHand[$core->id] ?? 0, $core->status,
                ]);
            }
            fclose($out);
        }, 'cores-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(): Builder
    {
        $search = trim($this->search);

        return CoreDefinition::query()
            ->when($this->archived, fn ($q) => $q->onlyTrashed())
            ->when($this->status === 'INACTIVE', fn ($q) => $q->where('status', '!=', 'ACTIVE'), fn ($q) => $q->when($this->status !== '', fn ($w) => $w->where('status', $this->status)))
            ->when($this->type !== '', fn ($q) => $q->where('core_type', $this->type))
            ->when($this->process !== '', fn ($q) => $q->where('process_type', $this->process))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%")->orWhere('mark_code', 'ilike', "%{$search}%")));
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\CoreBox> the boxes that make a core, archived ones left out */
    private function boxesOf(CoreDefinition $core)
    {
        return $core->cavities->pluck('coreBox')->filter()->unique('id')->sortBy('code')->values();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $cores = $this->filtered()->with('cavities.coreBox')->withCount('requirements')->orderBy('code')->paginate(20);
        $focus = $this->details ? ($cores->getCollection()->firstWhere('id', $this->focused) ?? $cores->getCollection()->first()) : null;
        $focus?->load('requirements.productRevision.product');

        return [
            'cores' => $cores,
            'focus' => $focus,
            'madeBy' => $cores->getCollection()->mapWithKeys(fn ($c) => [$c->id => $this->boxesOf($c)]),
            'onHand' => app(CoreLotService::class)->onHand(),
            'processes' => CoreDefinition::whereNotNull('process_type')->where('process_type', '!=', '')->distinct()->orderBy('process_type')->pluck('process_type'),
            'counts' => [
                '' => CoreDefinition::count(),
                'ACTIVE' => CoreDefinition::where('status', 'ACTIVE')->count(),
                'INACTIVE' => CoreDefinition::where('status', '!=', 'ACTIVE')->count(),
            ],
            'archivedCount' => CoreDefinition::onlyTrashed()->count(),
        ];
    }
};
?>

<div class="tk">
@include('partials.list-ui')
<style global>
    .tk { background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; font-size: 13px; color: #1c1d1f; }
    .tk button, .tk select, .tk input { font: inherit; min-height: 0; }
    .tk th.r, .tk td.r { text-align: right; }
    .tk code { font-family: ui-monospace, Menlo, monospace; font-size: 12.5px; background: none; padding: 0; }
    .tk .name { font-weight: 500; color: #1c1d1f; text-decoration: none; }
    .tk .pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; background: #f0f1f3; color: #55585d; }
    .tk .pill.ok { background: #e7f4ec; color: #1d7048; }
    .tk .pill.warn { background: #fbf0dc; color: #94610a; }
    .tk .muted { color: #6b6e73; }
    .tk-side { border-left: 1px solid #e6e7e9; padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 12px; }
    .tk-side h3 { margin: 0; font-size: 14px; font-weight: 600; }
    .tk-side h3 a { color: inherit; text-decoration: none; }
    .tk-side .row { display: flex; justify-content: space-between; gap: 12px; }
    .tk-side .row span:first-child { color: #6b6e73; }
    .tk-side .row span:last-child { text-align: right; }
    .tk-side .label { font-size: 12px; font-weight: 600; color: #6b6e73; text-transform: uppercase; letter-spacing: .06em; margin-top: 4px; }
    .tk-side .acts { display: flex; gap: 8px; flex-wrap: wrap; }
    .tk-note { padding: 10px 12px; border: 1px solid #f0d9a8; border-radius: 8px; background: #fffaf0; color: #94610a; font-size: 12.5px; }
    .tk-link { background: none; border: 0; padding: 0 0 0 6px; color: #2f5fd0; cursor: pointer; }
    .tk-foot { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 8px 20px; border-top: 1px solid #e6e7e9; color: #6b6e73; font-size: 12.5px; }
    .tk-foot a, .tk-foot button { color: #2f5fd0; background: none; border: 0; padding: 0; cursor: pointer; text-decoration: none; }
</style>

<div class="ls-head">
    <h1 class="ls-title">Cores</h1>
    <div class="ls-actions">
        <a class="ls-btn" href="{{ route('core-boxes.index') }}">Core boxes</a>
        @if (Route::has('core-lots.index'))<a class="ls-btn" href="{{ \App\Domains\Tooling\Support\CoreRules::stockOn() ? route('core-lots.index') : route('core-lots.create') }}">{{ \App\Domains\Tooling\Support\CoreRules::stockOn() ? 'Core stock' : 'Cores made' }}</a>@endif
        <a class="ls-btn" href="{{ route('imports.create', ['entity' => 'core_definition']) }}">Import</a>
        <a class="ls-btn primary" href="{{ route('cores.create') }}">New core</a>
    </div>
</div>
<nav class="ls-tabs" role="tablist" aria-label="Core status">
    @foreach (['' => 'All', 'ACTIVE' => 'Active', 'INACTIVE' => 'Inactive'] as $key => $label)
        <button type="button" role="tab" aria-selected="{{ ! $archived && $status === $key ? 'true' : 'false' }}" @class(['on' => ! $archived && $status === $key]) wire:click="showStatus('{{ $key }}')">{{ $label }}<span class="n">{{ Formats::number($counts[$key]) }}</span></button>
    @endforeach
</nav>
<div class="ls-tools" role="search">
    @include('partials.list-search', ['placeholder' => 'Search name, code or mark'])
    <x-list-filters :count="($type !== '' ? 1 : 0) + ($process !== '' ? 1 : 0)">
        <label>Type<select wire:model.live="type"><option value="">All</option>@foreach (CoreDefinition::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
        <label>Process<select wire:model.live="process"><option value="">All</option>@foreach ($processes as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach</select></label>
    </x-list-filters>
    @if ($search !== '' || $type !== '' || $process !== '' || $status !== '')<button class="ls-tool" type="button" wire:click="resetFilters">Reset</button>@endif
    <span class="muted" wire:loading>Updating…</span>
    <span class="ls-right">
        @include('partials.list-export')
        <span class="ls-sep" aria-hidden="true"></span>
        @include('partials.list-details-toggle')
    </span>
</div>

<div @class(['ls-body', 'with-panel' => $details && $focus])>
    <div class="ls-list" x-data @if ($details) x-on:keydown.arrow-down.prevent="($event.target.closest('tr')?.nextElementSibling)?.focus()" x-on:keydown.arrow-up.prevent="($event.target.closest('tr')?.previousElementSibling)?.focus()" @endif>
        @if ($cores->isEmpty())
            <p class="ls-empty">{{ $archived ? 'No archived cores.' : ($search !== '' || $type !== '' || $process !== '' || $status !== '' ? 'No cores found.' : 'No cores yet. Add a core, then the core box that makes it.') }}</p>
        @else
            <div class="ls-scroll">
                <table data-table="cores">
                    <thead>
                        <tr><th>Code</th><th>Name</th><th>Type</th><th>Process</th><th class="r">Weight kg</th><th>Made by</th><th class="r">Needed by</th><th class="r">On hand</th><th>Status</th>@unless ($details || $archived)<th><span class="sr-only">Actions</span></th>@endunless</tr>
                    </thead>
                    <tbody>
                        @foreach ($cores as $core)
                            @php $show = route('cores.show', $core); $boxes = $madeBy[$core->id]; @endphp
                            <tr wire:key="core-{{ $core->id }}-{{ $details ? 'p' : 'f' }}" tabindex="0" @class(['on' => $details && $focus && (string) $focus->id === (string) $core->id])
                                @if ($details) wire:click="focus('{{ $core->id }}')" x-on:focus="$wire.focus('{{ $core->id }}')" x-on:dblclick="window.location = '{{ $show }}'" x-on:keydown.enter="window.location = '{{ $show }}'"
                                @else x-on:click="if (! $event.target.closest('a,button,form,summary,details')) window.location = '{{ $show }}'" x-on:keydown.enter="window.location = '{{ $show }}'" @endif>
                                <td><code>{{ $core->code }}</code></td>
                                <td><a class="name" href="{{ $show }}" wire:click.stop>{{ $core->name }}</a></td>
                                <td>{{ CoreDefinition::TYPES[$core->core_type] ?? $core->core_type }}</td>
                                <td>{{ $core->process_type ?: '—' }}</td>
                                <td class="r">{{ $core->nominal_weight_kg !== null ? Formats::number((float) $core->nominal_weight_kg, 2) : '—' }}</td>
                                <td>{{ $boxes->isNotEmpty() ? $boxes->pluck('code')->implode(', ') : '—' }}</td>
                                <td class="r">{{ $core->requirements_count ?: '—' }}</td>
                                <td class="r">{{ Formats::number($onHand[$core->id] ?? 0) }}</td>
                                <td>
                                    @if ($archived)<span class="pill">Archived</span> <form method="POST" action="{{ route('cores.restore', $core) }}" style="display:inline" wire:click.stop>@csrf<button class="tk-link" type="submit">Restore</button></form>
                                    @elseif ($core->status === 'ACTIVE')<span class="pill ok">Active</span>
                                    @else<span class="pill">Inactive</span>@endif
                                </td>
                                @unless ($details || $archived)
                                    <td class="act"><details class="ls-menu right"><summary class="ls-more" aria-label="Actions for {{ $core->code }}">⋯</summary>
                                        <div class="ls-pop" role="menu"><a role="menuitem" href="{{ $show }}">Open core</a>
                                            <a role="menuitem" href="{{ route('cores.edit', $core) }}">Edit</a>
                                            @if (Route::has('core-lots.create'))<a role="menuitem" href="{{ route('core-lots.create', ['core' => $core->id]) }}">Record cores made</a>@endif</div></details></td>
                                @endunless
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <div class="tk-foot">
            <span>@if ($cores->isNotEmpty())Showing {{ $cores->firstItem() }}–{{ $cores->lastItem() }} of {{ $cores->total() }}@endif</span>
            <span class="pagination-links">{{ $cores->links() }}</span>
            <button type="button" wire:click="$toggle('archived')">{{ $archived ? 'Back to current cores' : $archivedCount.' archived' }}</button>
        </div>
    </div>

    @if ($details && $focus)
        @php $boxes = $madeBy[$focus->id]; @endphp
        <aside class="tk-side ls-panel" aria-label="Summary of {{ $focus->name }}">
            <div class="ls-ph"><h3><a href="{{ route('cores.show', $focus) }}">{{ $focus->code }} · {{ $focus->name }}</a></h3>@include('partials.list-hide-panel')</div>
            <div class="muted">{{ collect([CoreDefinition::TYPES[$focus->core_type] ?? $focus->core_type, $focus->process_type])->filter()->implode(' · ') }}</div>
            <div class="acts">
                <a class="ls-btn" href="{{ route('cores.show', $focus) }}">Open core</a>
                @unless ($archived)<a class="ls-btn" href="{{ route('core-boxes.create', ['core' => $focus->id]) }}">+ Core box</a>@endunless
            </div>
            <div class="row"><span>Weight</span><span>{{ $focus->nominal_weight_kg !== null ? Formats::number((float) $focus->nominal_weight_kg, 2).' kg' : '—' }}</span></div>
            <div class="row"><span>Mark code</span><span>{{ $focus->mark_code ?: '—' }}</span></div>
            <div class="row"><span>On hand</span><span>{{ Formats::number($onHand[$focus->id] ?? 0) }} cores</span></div>
            <div class="row"><span>Status</span><span>{{ $focus->status === 'ACTIVE' ? 'Active' : 'Inactive' }}</span></div>
            <div class="label">Core boxes that make it</div>
            @forelse ($boxes as $box)
                <div class="row"><span><a href="{{ route('core-boxes.show', $box) }}"><code>{{ $box->code }}</code></a> {{ $box->name }} · Rev {{ $box->revision }}</span><span>{{ $focus->cavities->where('core_box_id', $box->id)->count() }} of {{ $box->cavities }}</span></div>
            @empty
                <div class="muted">No core box makes it yet, or it is bought in.</div>
            @endforelse
            <div class="label">Needed by</div>
            @forelse ($focus->requirements as $need)
                @php $product = $need->productRevision?->product; @endphp
                <div class="row"><span>@if ($product)<a href="{{ route('products.show', $product) }}"><code>{{ $product->code }}</code></a> {{ $product->name }} rev {{ $need->productRevision->revision }}@else — @endif</span><span>× {{ $need->quantity_per_casting }}</span></div>
            @empty
                <div class="muted">No product needs it yet.</div>
            @endforelse
        </aside>
    @endif
</div>
</div>
