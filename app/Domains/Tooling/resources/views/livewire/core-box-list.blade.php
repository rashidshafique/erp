<?php

use App\Domains\Tooling\Models\ToolingLifeLimit;
use App\Domains\Tooling\Models\ToolingMaintenanceRecord;
use App\Domains\Tooling\Services\ToolingLifeStatusService;
use App\Models\CoreBox;
use App\Support\Formats;
use App\Support\ListDetails;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The core boxes list (design 29d): status tabs (In use, Lent, Inactive, Retired), search, owner and custody filters, and a Details
 * toggle. Lent is an active box given to a third party who makes the cores with it. With Details on, a row click shows the box in the
 * side panel with the cores it makes, its life and where it is; with it off, a row click opens the box.
 */
new class extends Component
{
    use ListDetails;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** '', ACTIVE (in use, not lent), LENT, INACTIVE or RETIRED. */
    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $owner = '';

    #[Url(except: '')]
    public string $custody = '';

    #[Url(except: false)]
    public bool $archived = false;

    /** The box whose summary shows in the side panel. */
    public ?string $focused = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'owner', 'custody', 'archived'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'owner', 'custody');
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

    public function focus(string $boxId): void
    {
        $this->focused = $boxId;
    }

    /** Downloads every box that matches the filters, not only this page, as CSV. */
    public function export(): StreamedResponse
    {
        $boxes = $this->filtered()->with('cavityMap.coreDefinition', 'ownerCustomer')->orderBy('code')->get();

        return response()->streamDownload(function () use ($boxes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Name', 'Revision', 'Mark', 'Makes', 'Cavities', 'Origin', 'Owner', 'Where it is', 'Commissioned', 'Status']);
            foreach ($boxes as $box) {
                fputcsv($out, [
                    $box->code, $box->name, $box->revision, $box->mark_code, $box->makesLine(), $box->cavities, CoreBox::ORIGINS[$box->origin] ?? $box->origin,
                    $box->ownerLabel(), $box->custodyLabel(), $box->commissioned_on?->format('Y-m-d'), CoreBox::STATUS_LABELS[$box->displayStatus()] ?? $box->status,
                ]);
            }
            fclose($out);
        }, 'core-boxes-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Boxes in a status tab: In use leaves out lent boxes, which have a tab of their own. */
    private function inStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'ACTIVE' => $query->where('status', 'ACTIVE')->where(fn ($q) => $q->whereNull('custody')->orWhere('custody', '!=', 'LENT')),
            'LENT' => $query->where('status', 'ACTIVE')->where('custody', 'LENT'),
            '' => $query,
            default => $query->where('status', $status),
        };
    }

    private function filtered(): Builder
    {
        $search = trim($this->search);

        return $this->inStatus(CoreBox::query(), $this->status)
            ->when($this->archived, fn ($q) => $q->onlyTrashed())
            ->when($this->owner !== '', fn ($q) => $q->where('owner_type', $this->owner))
            ->when($this->custody !== '', fn ($q) => $q->where('custody', $this->custody))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%")->orWhere('mark_code', 'ilike', "%{$search}%")));
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $boxes = $this->filtered()->with('cavityMap.coreDefinition', 'ownerCustomer')->orderBy('code')->paginate(20);
        $focus = $this->details ? ($boxes->getCollection()->firstWhere('id', $this->focused) ?? $boxes->getCollection()->first()) : null;
        $life = null;
        $openRecord = null;
        if ($focus) {
            $focus->load('modifiedFromBox', 'modifiedFromPattern');
            $limit = ToolingLifeLimit::where('core_box_id', $focus->id)->where('is_active', true)->first();
            $life = $limit ? ['limit' => $limit, 'used' => app(ToolingLifeStatusService::class)->calculate($limit)] : null;
            $openRecord = ToolingMaintenanceRecord::where('core_box_id', $focus->id)->whereIn('status', ['OPEN', 'IN_PROGRESS'])->latest('reported_at')->first();
        }

        return [
            'boxes' => $boxes,
            'focus' => $focus,
            'life' => $life,
            'openRecord' => $openRecord,
            'counts' => collect(['', 'ACTIVE', 'LENT', 'INACTIVE', 'RETIRED'])->mapWithKeys(fn ($s) => [$s => $this->inStatus(CoreBox::query(), $s)->count()]),
            'archivedCount' => CoreBox::onlyTrashed()->count(),
        ];
    }
};
?>

<div class="tk">
@include('partials.list-ui')
<style global>
    .tk { background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; font-size: 13px; color: #1c1d1f; }
    .tk button, .tk select, .tk input { font: inherit; min-height: 0; }
    .tk .ls-list tbody tr.on { background: #f5f8fe; }
    .tk th.r, .tk td.r { text-align: right; }
    .tk code { font-family: ui-monospace, Menlo, monospace; font-size: 12.5px; background: none; padding: 0; }
    .tk .name { font-weight: 500; color: #1c1d1f; text-decoration: none; }
    .tk .pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; background: #f0f1f3; color: #55585d; }
    .tk .pill.ok { background: #e7f4ec; color: #1d7048; }
    .tk .pill.info { background: #eaf0fd; color: #2f5fd0; }
    .tk .pill.warn { background: #fbf0dc; color: #94610a; }
    .tk .muted { color: #6b6e73; }
    .tk-side { border-left: 1px solid #e6e7e9; padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 12px; }
    .tk-side h3 { margin: 0; font-size: 13px; font-weight: 600; }
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
    <h1 class="ls-title">Core boxes</h1>
    <div class="ls-actions">
        <a class="ls-btn" href="{{ route('cores.index') }}">Cores</a>
        <a class="ls-btn" href="{{ route('imports.create', ['entity' => 'core_box']) }}">Import</a>
        <a class="ls-btn primary" href="{{ route('core-boxes.create') }}">New core box</a>
    </div>
</div>
<nav class="ls-tabs" role="tablist" aria-label="Core box status">
    @foreach (['' => 'All'] + CoreBox::STATUS_LABELS as $key => $label)
        <button type="button" role="tab" aria-selected="{{ ! $archived && $status === $key ? 'true' : 'false' }}" @class(['on' => ! $archived && $status === $key]) wire:click="showStatus('{{ $key }}')">{{ $label }}<span class="n">{{ Formats::number($counts[$key]) }}</span></button>
    @endforeach
</nav>
<div class="ls-tools" role="search">
    @include('partials.list-search', ['placeholder' => 'Search name, code or mark'])
    <x-list-filters :count="($owner !== '' ? 1 : 0) + ($custody !== '' ? 1 : 0)">
        <label>Owner<select wire:model.live="owner"><option value="">All</option><option value="FOUNDRY">Foundry</option><option value="CUSTOMER">Customer</option></select></label>
        <label>Where<select wire:model.live="custody"><option value="">All</option>@foreach (CoreBox::CUSTODY_LABELS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
    </x-list-filters>
    @if ($search !== '' || $owner !== '' || $custody !== '' || $status !== '')<button class="ls-tool" type="button" wire:click="resetFilters">Reset</button>@endif
    <span class="muted" wire:loading>Updating…</span>
    <span class="ls-right">
        @include('partials.list-export')
        <span class="ls-sep" aria-hidden="true"></span>
        @include('partials.list-details-toggle')
    </span>
</div>

<div @class(['ls-body', 'with-panel' => $details && $focus])>
    <div class="ls-list" x-data @if ($details) x-on:keydown.arrow-down.prevent="($event.target.closest('tr')?.nextElementSibling)?.focus()" x-on:keydown.arrow-up.prevent="($event.target.closest('tr')?.previousElementSibling)?.focus()" @endif>
        @if ($boxes->isEmpty())
            <p class="ls-empty">{{ $archived ? 'No archived core boxes.' : ($search !== '' || $owner !== '' || $custody !== '' || $status !== '' ? 'No core boxes found.' : 'No core boxes yet. Add the box that makes a core.') }}</p>
        @else
            <div class="ls-scroll">
                <table data-table="core-boxes">
                    <thead>
                        <tr><th>Code</th><th>Name</th><th>Rev</th><th>Makes</th><th class="r">Cavities</th><th>Owner / where it is</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($boxes as $box)
                            @php $show = route('core-boxes.show', $box); $shown = $box->displayStatus(); @endphp
                            <tr wire:key="box-{{ $box->id }}-{{ $details ? 'p' : 'f' }}" tabindex="0" @class(['on' => $details && $focus && (string) $focus->id === (string) $box->id])
                                @if ($details) wire:click="focus('{{ $box->id }}')" x-on:focus="$wire.focus('{{ $box->id }}')" x-on:dblclick="window.location = '{{ $show }}'" x-on:keydown.enter="window.location = '{{ $show }}'"
                                @else x-on:click="if (! $event.target.closest('a,button,form')) window.location = '{{ $show }}'" x-on:keydown.enter="window.location = '{{ $show }}'" @endif>
                                <td><code>{{ $box->code }}</code></td>
                                <td><a class="name" href="{{ $show }}" wire:click.stop>{{ $box->name }}</a></td>
                                <td>{{ $box->revision }}</td>
                                <td>{{ $box->makesLine() ?: '—' }}</td>
                                <td class="r">{{ $box->cavities }}</td>
                                <td>{{ $box->owner_type === 'CUSTOMER' && $box->ownerCustomer ? $box->ownerCustomer->name : 'Foundry' }} · {{ $shown === 'LENT' && $box->custody_location ? 'Lent · '.$box->custody_location : (CoreBox::CUSTODY_LABELS[$box->custody] ?? '—') }}</td>
                                <td>
                                    @if ($archived)<span class="pill">Archived</span> <form method="POST" action="{{ route('core-boxes.restore', $box) }}" style="display:inline" wire:click.stop>@csrf<button class="tk-link" type="submit">Restore</button></form>
                                    @else<span @class(['pill', 'ok' => $shown === 'ACTIVE', 'info' => $shown === 'LENT'])>{{ CoreBox::STATUS_LABELS[$shown] ?? $shown }}</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <div class="tk-foot">
            <span>@if ($boxes->isNotEmpty())Showing {{ $boxes->firstItem() }}–{{ $boxes->lastItem() }} of {{ $boxes->total() }}@endif</span>
            <span class="pagination-links">{{ $boxes->links() }}</span>
            <button type="button" wire:click="$toggle('archived')">{{ $archived ? 'Back to current core boxes' : $archivedCount.' archived' }}</button>
        </div>
    </div>

    @if ($details && $focus)
        @php $shown = $focus->displayStatus(); @endphp
        <aside class="tk-side ls-panel" aria-label="Summary of {{ $focus->name }}">
            <div class="ls-ph"><h3><a href="{{ route('core-boxes.show', $focus) }}">{{ $focus->code }} · {{ $focus->name }}</a></h3>@include('partials.list-hide-panel')</div>
            <div class="muted">Rev {{ $focus->revision }} · {{ CoreBox::STATUS_LABELS[$shown] ?? $shown }}</div>
            <div class="acts">
                <a class="ls-btn" href="{{ route('core-boxes.show', $focus) }}">Open core box</a>
                @unless ($archived)<a class="ls-btn" href="{{ route('core-boxes.edit', $focus) }}">Edit</a>@endunless
            </div>
            @if ($openRecord)<div class="tk-note">{{ \Illuminate\Support\Str::limit($openRecord->problem_description ?: ucfirst(strtolower(str_replace('_', ' ', $openRecord->activity_type))), 80) }}. <a href="{{ route('tooling-maintenance.show', $openRecord) }}">{{ $openRecord->record_number }}</a> open since {{ $openRecord->reported_at?->displayDate() }}.</div>@endif
            <div class="label">Cores it makes</div>
            @foreach ($focus->coreCounts() as $row)
                <div class="row"><span>@if ($row['core'])<a href="{{ route('cores.show', $row['core']) }}"><code>{{ $row['core']->code }}</code></a> {{ $row['core']->name }}@else — @endif</span><span>× {{ $row['qty'] }}</span></div>
            @endforeach
            @if ($focus->blockedCavities() > 0)<div class="row"><span>Blocked</span><span>× {{ $focus->blockedCavities() }}</span></div>@endif
            @if ($life)
                <div class="row"><span>Life used</span><span>{{ Formats::number($life['used']['current_usage'], 0) }} of {{ Formats::number((float) $life['limit']->service_life_quantity, 0) }} {{ strtolower(str_replace('_', ' ', $life['limit']->usage_unit)) }}</span></div>
            @endif
            <div class="label">Box</div>
            <div class="row"><span>Owner</span><span>{{ $focus->ownerLabel() }}</span></div>
            <div class="row"><span>Where it is</span><span>{{ $focus->custodyLabel() }}</span></div>
            <div class="row"><span>Cavities</span><span>{{ $focus->cavities }}@if ($focus->blockedCavities() > 0) · {{ $focus->blockedCavities() }} blocked @endif</span></div>
            <div class="row"><span>Commissioned</span><span>{{ $focus->commissioned_on?->displayDate() ?? '—' }}</span></div>
            <div class="row"><span>Comes from</span><span>
                @if ($focus->modifiedFromBox)Modified from <a href="{{ route('core-boxes.show', $focus->modifiedFromBox) }}">{{ $focus->modifiedFromBox->code }}</a>
                @elseif ($focus->modifiedFromPattern)Modified from <a href="{{ route('patterns.show', $focus->modifiedFromPattern) }}">{{ $focus->modifiedFromPattern->code }}</a>
                @else{{ CoreBox::ORIGINS[$focus->origin] ?? '—' }}@endif
            </span></div>
        </aside>
    @endif
</div>
</div>
