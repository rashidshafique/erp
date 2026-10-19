@extends('layouts.app')

@section('title', $pattern->name.' · '.$pattern->code)
@section('breadcrumb', 'Patterns / ' . $pattern->code)

@section('content')
@php $typeLabel = ucfirst(strtolower(str_replace('_', ' ', $pattern->pattern_type ?? 'other'))); @endphp
<div class="page-heading">
    <div class="pattern-title">
        <h1>{{ $pattern->name }}</h1>
        <p><span class="code">{{ $pattern->code }}</span><span class="dot-sep">·</span>{{ $typeLabel }}<span class="dot-sep">·</span><span class="status {{ $pattern->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled' }}">{{ $pattern->status }}</span><span class="dot-sep">·</span><span title="Owner">{{ $pattern->owner_type === 'FOUNDRY' ? 'Foundry-owned' : 'Property of '.$pattern->ownerLabel() }}</span></p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('patterns.index') }}">All patterns</a>
        @if ($pattern->trashed())
            <form method="POST" action="{{ route('patterns.restore', $pattern) }}" style="display:inline">@csrf<button class="btn btn-primary" type="submit">Restore pattern</button></form>
        @else
            <form method="POST" action="{{ route('patterns.archive', $pattern) }}" style="display:inline" onsubmit="return confirm(@js('Archive '.$pattern->code.'? It leaves the pattern list and pickers but stays on old runs, and can be restored.'))">@csrf @method('DELETE')<button class="btn" type="submit">Archive</button></form>
            <a class="btn" href="{{ route('patterns.edit', $pattern) }}">Edit pattern</a>
            <button type="button" class="btn btn-primary" data-quick="qd-revision">+ New revision</button>
        @endif
    </div>
</div>
@if ($pattern->trashed() && in_array($pattern->custody, ['RETURNED', 'SCRAPPED'], true))<div class="alert alert-warning">{{ $pattern->custody === 'RETURNED' ? 'Returned to '.$pattern->ownerLabel() : 'Scrapped' }} on {{ $pattern->deleted_at->displayDate() }}. It cannot be moulded.@if ($pattern->custody === 'RETURNED') Restore it if the customer sends it back.@endif</div>
@elseif ($pattern->trashed())<div class="alert alert-warning">Archived on {{ $pattern->deleted_at->displayDate() }}. It is left out of the pattern list, its counts and the moulding pickers, and stays on the runs and tooling records that used it. Restore it to use it again.</div>@endif

@php $current = $pattern->revisions->firstWhere('is_current', true); @endphp
@include('partials.quick-dialog', ['id' => 'qd-revision', 'title' => 'New revision of '.$pattern->code, 'action' => route('patterns.revisions.store', $pattern), 'submit' => 'Create revision', 'fields' => 'patterns._quick-revision', 'multipart' => true, 'current' => $current])

<div class="tab-marker" data-tab-start="Tooling"></div>
<style>
    .pattern-title h1 { font-size: 26px; line-height: 1.2; margin: 0 0 4px; }
    .pattern-title p { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 0; color: var(--muted); font-size: 13px; }
    .pattern-title .code { font: 600 13px ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--text); letter-spacing: .02em; }
    .pattern-title .dot-sep { color: var(--border); }
    .tool-layout { display: grid; grid-template-columns: 170px minmax(0, 1fr) 260px; gap: 20px 28px; align-items: start; }
    .tool-photo > .cover-box { float: none; margin: 0; width: 100% !important; }
    .tool-facts { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 16px 24px; margin: 0; }
    .tool-facts .fact-wide { grid-column: 1 / -1; }
    .tool-facts dt { margin: 0 0 4px; font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
    .tool-facts dd { margin: 0; font-size: 14px; font-weight: 600; line-height: 1.35; overflow-wrap: anywhere; }
    .tool-facts dd details, .tool-facts dd a { font-size: 12px; font-weight: 400; }
    .tool-facts dd details summary { color: var(--muted); }
    .tool-facts dd .sub { display: block; margin-top: 2px; font-size: 12px; font-weight: 400; color: var(--muted); }
    .tool-diagram { justify-self: center; padding: 6px 0; }
    .tool-notes { display: grid; gap: 14px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); }
    .tool-notes h3 { margin: 0 0 4px; font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
    .tool-notes p { margin: 0; max-width: 75ch; line-height: 1.5; }
    @media (max-width: 1024px) { .tool-layout { grid-template-columns: 150px minmax(0, 1fr); } .tool-diagram { grid-column: 1 / -1; justify-self: start; } }
    @media (max-width: 768px) { .tool-layout { grid-template-columns: 1fr; } .tool-photo { max-width: 200px; } }
</style>
<div class="panel">
    <div class="panel-header"><h2>Physical tooling record</h2></div>
    <div class="panel-body">
        <div class="tool-layout">
            <div class="tool-photo">@include('attachments._cover', ['type' => 'pattern', 'id' => $pattern->id, 'size' => 170])</div>
            <dl class="tool-facts">
                <div><dt>Pattern code</dt><dd class="mono">{{ $pattern->code }}</dd></div>
                <div><dt>Type</dt><dd>{{ $typeLabel }}</dd></div>
                <div><dt>Material</dt><dd>{{ $pattern->material ?: '—' }}</dd></div>
                @php $mm = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); @endphp
                <div><dt>Dimensions (L × W × H)</dt><dd>@if ($pattern->length_mm || $pattern->width_mm || $pattern->height_mm){{ $mm($pattern->length_mm) }} × {{ $mm($pattern->width_mm) }} × {{ $mm($pattern->height_mm) }} mm @else — @endif</dd></div>
                <div><dt>Made for sand</dt><dd>{{ $pattern->sand_system ?: 'Not recorded' }}@if ($pattern->compatible_sands)<span class="sub">also works on {{ implode(', ', $pattern->compatible_sands) }}</span>@endif</dd></div>
                <div><dt>Current revision</dt><dd>@if ($current)<span class="mono">{{ $current->code }}</span>@if ($current->drawing_revision)<span class="sub">drawing rev {{ $current->drawing_revision }}</span>@endif @else — @endif</dd></div>
                @php $madeProducts = $current ? $current->cavityMap->map(fn ($c) => $c->productRevision?->product)->filter()->unique('id') : collect(); @endphp
                @if ($madeProducts->isNotEmpty())<div class="fact-wide"><dt>Makes</dt><dd>@foreach ($madeProducts as $made)<a href="{{ route('products.show', $made) }}">{{ $made->name }}</a> <span class="muted small mono">{{ $made->code }}</span>@if (! $loop->last)<span class="dot-sep"> · </span>@endif @endforeach</dd></div>@endif
                <div class="fact-wide"><dt>Engraved mark</dt><dd>@include('tooling::tools._mark', ['tool' => $pattern, 'type' => 'pattern', 'bare' => true, 'optional' => true])</dd></div>
            </dl>
            <div class="tool-diagram">@include('patterns._mould-diagram', ['revision' => $current, 'size' => 'lg'])</div>
        </div>

        @if ($pattern->description || $pattern->notes)
            <div class="tool-notes">
                @if ($pattern->description)<div><h3>Description</h3><p>{{ $pattern->description }}</p></div>@endif
                @if ($pattern->notes)<div><h3>General tooling notes</h3><p>{{ $pattern->notes }}</p></div>@endif
            </div>
        @endif
    </div>
</div>

@if (app(\App\Domains\Core\ModuleRegistry::class)->isEnabled('tooling'))
@include('patterns._tool-life', ['pattern' => $pattern, 'toolLife' => $toolLife])
@endif

<div class="tab-marker" data-tab-start="Sand system"></div>
<div class="panel"><div class="panel-header"><h2>Sand system</h2></div><div class="panel-body">
    <p class="muted small" style="margin-top:0">A pattern is made with the taper and allowances of one sand system. The work list warns when it is put on a line running a sand system that is not listed here.</p>
    <form method="POST" action="{{ route('patterns.sand', $pattern) }}" class="form-grid">@csrf @method('PUT')
        @include('patterns._sand', ['sands' => \App\Models\SandSystem::choices($pattern->sand_system, ...($pattern->compatible_sands ?? []))])
        <div><button class="btn btn-primary">Save sand system</button></div>
    </form>
</div></div>

<div class="tab-marker" data-tab-start="Revisions"></div>
<div class="panel">
    <div class="panel-header"><h2>Tooling revisions</h2><span class="muted small">One current revision; older ones are superseded and kept. Repairs and repainting go in the maintenance log, not here.</span></div>

    @if ($pattern->revisions->isEmpty())
        <div class="empty-state">No revisions recorded. Press “+ New revision” to record the first one.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>State</th>
                        <th>Approval</th>
                        <th>Drawing rev</th>
                        <th>Cost (PKR)</th>
                        <th>Casting / pour kg</th>
                        <th>Layout</th>
                        <th>Parting</th>
                        <th>Shrink / tolerance</th>
                        <th>Effective</th>
                        <th>Photo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pattern->revisions as $revision)
                        <tr>
                            <td><strong class="mono">{{ $revision->shortCode() }}</strong>@unless ($pattern->trashed())<div class="small"><a href="{{ route('patterns.revisions.edit', [$pattern, $revision]) }}">Edit details</a></div>@endunless</td>
                            <td>
                                @if ($revision->is_current)<span class="status status-completed">Current</span>
                                @elseif ($revision->isSuperseded())<span class="status status-on-hold" title="Superseded {{ $revision->superseded_at->displayDate() }}">Superseded</span>
                                @else<span class="status">Not current</span>@endif
                                @if ($revision->legacy_baseline)<span class="status" title="Created from the imported data, not from a recorded change">Baseline</span>@endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('patterns.revisions.status', [$pattern, $revision]) }}" style="display:flex;gap:4px;align-items:center">
                                    @csrf @method('PUT')
                                    <select name="status" onchange="this.form.submit()" aria-label="Revision status">
                                        @foreach (['DRAFT', 'APPROVED', 'OBSOLETE'] as $state)<option value="{{ $state }}" @selected($revision->status === $state)>{{ ucfirst(strtolower($state)) }}</option>@endforeach
                                    </select>
                                </form>
                            </td>
                            <td>{{ $revision->drawing_revision ?: '—' }}</td>
                            <td class="mono">@if ($revision->modification_cost !== null){{ \App\Support\Formats::number((float) $revision->modification_cost) }}@elseif ($revision->legacy_baseline)<span class="muted">not recorded</span>@else — @endif</td>
                            <td class="mono small">@if ($revision->casting_weight_kg || $revision->pour_weight_kg){{ $revision->casting_weight_kg !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $revision->casting_weight_kg, 3), '0'), '.') : '—' }} / {{ $revision->pour_weight_kg !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $revision->pour_weight_kg, 3), '0'), '.') : '—' }}@if ($revision->yieldPercent() !== null)<div class="muted">yield {{ $revision->yieldPercent() }} %@if ($revision->pieces_per_mould) · {{ $revision->pieces_per_mould }} pcs/mould @endif</div>@endif @elseif ($revision->legacy_baseline)<span class="muted">not recorded</span>@else — @endif</td>
                            <td class="small" title="{{ $revision->arrangementLabel() }}">{{ $revision->castingLevels() }} × {{ $revision->cavities_per_mould ?? '?' }}<div class="muted">{{ \Illuminate\Support\Str::before($revision->arrangementLabel(), ' (') }}</div></td>
                            <td>{{ $revision->parting_type ?: '—' }}</td>
                            <td class="small">@if ($revision->shrinkage_percent !== null || $revision->tolerance_grade || $revision->machining_allowance_grade){{ $revision->shrinkage_percent !== null ? rtrim(rtrim($revision->shrinkage_percent, '0'), '.').' %' : '—' }}<div class="muted">{{ $revision->tolerance_grade ? 'CT '.$revision->tolerance_grade : '—' }} · {{ $revision->machining_allowance_grade ? 'RMAG '.$revision->machining_allowance_grade : '—' }}</div>@else — @endif</td>
                            <td class="small">{{ $revision->effective_from?->displayDate() ?? '—' }} – {{ $revision->effective_to?->displayDate() ?? '' }}</td>
                            <td>@foreach ($revisionPhotos->get($revision->id, collect()) as $photo)<a href="{{ route('attachments.download', $photo) }}" target="_blank">View</a> @endforeach</td>
                        </tr>
                        @if ($revision->change_description || $revision->shrinkage_note || $revision->engineering_notes || $revision->cost_note)
                            <tr>
                                <td colspan="11" class="small">
                                    @if ($revision->change_description)<strong>Change:</strong> {{ $revision->change_description }}@endif
                                    @if ($revision->cost_note)<div class="muted"><strong>Cost note:</strong> {{ $revision->cost_note }}</div>@endif
                                    @if ($revision->shrinkage_note)<div class="muted"><strong>Shrinkage / allowance:</strong> {{ $revision->shrinkage_note }}</div>@endif
                                    @if ($revision->engineering_notes)<div class="muted"><strong>Engineering notes:</strong> {{ $revision->engineering_notes }}</div>@endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="tab-marker" data-tab-start="Ownership"></div>
@include('patterns._ownership', ['pattern' => $pattern, 'owner' => $owner])

<div class="tab-marker" data-tab-start="Cavity map"></div>
@include('patterns._cavity-map', ['pattern' => $pattern, 'productRevisions' => $productRevisions])

<div class="tab-marker" data-tab-start="Cooling"></div>
@include('patterns._cooling', ['pattern' => $pattern])
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'pattern', 'id' => $pattern->id])
@include('attachments._panel', ['type' => 'pattern', 'id' => $pattern->id])
@include('patterns._history', ['pattern' => $pattern])
@endsection
