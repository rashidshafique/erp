@extends('layouts.app')

@section('title', $pattern->name.' · '.$pattern->code)
@section('breadcrumb', 'Patterns / ' . $pattern->code)

@section('content')
@php
    $typeLabel = \App\Models\Pattern::typeName($pattern->pattern_type, true);
    $current = $pattern->revisions->firstWhere('is_current', true);
    $madeProducts = $current ? $current->cavityMap->map(fn ($c) => $c->productRevision?->product)->filter()->unique('id') : collect();
    $levels = $current?->castingLevels() ?? 1;
    $closedCavities = $current ? $current->cavityMap->where('is_closed', true)->count() : 0;
    $cavities = $current ? max(0, (int) ($current->cavities_per_mould ?? $current->cavityMap->count()) - $closedCavities) : 0;
    $flask = $current?->flaskSizes->first();
    $sections = (int) ($current?->layers_per_mould ?? 2);
    $station = $pattern->preferredStation;
    $cooling = $current?->min_cooling_minutes;
    $mm = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $canEditInPlace = ! $pattern->trashed() && \App\Http\Middleware\EnforceAccess::allows(auth()->user(), 'patterns.update');
    $editingInPlace = $canEditInPlace && old('_inplace') !== null && $errors->any();
@endphp
<style>
    .pd-head { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }
    .pd-top { display: flex; flex-wrap: wrap; gap: 12px 20px; justify-content: space-between; align-items: flex-start; }
    .pd-title { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .pd-title h1 { font-size: 26px; line-height: 1.2; margin: 0 6px 0 0; }
    .pd-badge { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; border: 1px solid var(--border); color: var(--muted); }
    .pd-badge.ok { color: var(--success, #067647); background: color-mix(in srgb, var(--success, #067647) 10%, transparent); border-color: color-mix(in srgb, var(--success, #067647) 30%, transparent); }
    .pd-badge.off { color: var(--danger); background: color-mix(in srgb, var(--danger) 8%, transparent); border-color: color-mix(in srgb, var(--danger) 30%, transparent); }
    .pd-badge.owner { color: var(--accent); background: color-mix(in srgb, var(--accent) 8%, transparent); border-color: color-mix(in srgb, var(--accent) 30%, transparent); }
    .pd-badge.type { gap: 5px; font: 500 12px ui-monospace, SFMono-Regular, Menlo, monospace; border-radius: 6px; }
    .pd-meta { display: flex; flex-wrap: wrap; gap: 6px 22px; margin-top: 8px; font: 13px ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--muted); }
    .pd-meta strong { color: var(--text); font-weight: 600; }
    .pd-meta .stamp { padding: 0 6px; border: 1px solid color-mix(in srgb, var(--warning, #b54708) 50%, transparent); background: color-mix(in srgb, var(--warning, #b54708) 8%, transparent); border-radius: 4px; color: var(--text); letter-spacing: .1em; }
    .pd-makes { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 10px; }
    .pd-makes-label { font: 13px ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--muted); margin-right: 2px; }
    .pd-product { display: inline-flex; align-items: center; gap: 8px; padding: 4px 10px 4px 4px; border: 1px solid var(--border); border-radius: 8px; color: var(--text); text-decoration: none; }
    .pd-product:hover { border-color: var(--accent); }
    .pd-product img, .pd-product .pd-noimg { width: 36px; height: 36px; flex: none; border-radius: 6px; object-fit: cover; padding: 0; min-height: 0; }
    .pd-product .pd-noimg { display: inline-flex; align-items: center; justify-content: center; color: var(--muted); background: color-mix(in srgb, var(--border) 50%, transparent); }
    .pd-product > span { display: flex; flex-direction: column; line-height: 1.25; }
    .pd-product strong { font-size: 13px; font-weight: 600; }
    .pd-product small { font: 11.5px ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--muted); }
    .pd-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; align-items: center; }
    .pd-actions .pd-nav { display: inline-flex; }
    .pd-actions .pd-nav .btn:first-child { border-top-right-radius: 0; border-bottom-right-radius: 0; }
    .pd-actions .pd-nav .btn:last-child { border-top-left-radius: 0; border-bottom-left-radius: 0; margin-left: -1px; }
    .pd-tiles { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 0; margin: 16px 0 0; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
    .pd-tile { padding: 12px 16px; min-width: 0; border-left: 1px solid var(--border); }
    .pd-tile:first-child { border-left: 0; }
    .pd-tile dt { font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
    .pd-tile dd { margin: 6px 0 0; font-size: 16px; font-weight: 700; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; }
    .pd-tile dd.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 15px; }
    .pd-tile .sub { display: block; margin-top: 4px; font-size: 12px; font-weight: 400; color: var(--muted); white-space: normal; }
    .pd-tile .sub.good { color: var(--success, #067647); }
    .pd-tile .sub.link { color: var(--accent); }
    @media (max-width: 1280px) {
        .pd-tiles { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .pd-tile:nth-child(3n + 1) { border-left: 0; }
        .pd-tile:nth-child(n + 4) { border-top: 1px solid var(--border); }
    }
    @media (max-width: 640px) {
        .pd-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pd-tile:nth-child(3n + 1) { border-left: 1px solid var(--border); }
        .pd-tile:nth-child(2n + 1) { border-left: 0; }
        .pd-tile:nth-child(n + 3) { border-top: 1px solid var(--border); }
        .pd-actions { justify-content: flex-start; }
    }

    .pd-dossier { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 16px; align-items: start; }
    .pd-side { display: grid; gap: 16px; }
    .pd-side .panel { margin: 0; }
    .pd-side .form-grid { grid-template-columns: 1fr; }
    @media (max-width: 1100px) { .pd-dossier { grid-template-columns: 1fr; } }

    [data-inplace] [hidden] { display: none !important; }
    .tool-photo > .cover-box { float: none; margin: 0 0 14px; width: 100% !important; }
    .tool-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 0; }
    .tool-facts > div { background: color-mix(in srgb, var(--border) 25%, transparent); border-radius: 8px; padding: 10px 12px; min-width: 0; }
    .tool-facts .fact-wide { grid-column: 1 / -1; }
    .tool-facts dt { margin: 0 0 4px; font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
    .tool-facts dd { margin: 0; font-size: 14px; font-weight: 600; line-height: 1.35; overflow-wrap: anywhere; }
    .tool-facts dd details, .tool-facts dd a { font-size: 12px; font-weight: 400; }
    .tool-facts dd details summary { color: var(--muted); }
    .tool-facts dd .sub { display: block; margin-top: 2px; font-size: 12px; font-weight: 400; color: var(--muted); }
    .tool-notes { display: grid; gap: 12px; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border); }
    .tool-notes h3 { margin: 0 0 4px; font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
    .tool-notes p { margin: 0; line-height: 1.5; }
</style>

<div class="pd-head">
    <div class="pd-top">
        <div>
            <div class="pd-title">
                <h1>{{ $pattern->name }}</h1>
                <span class="pd-badge {{ $pattern->status === 'ACTIVE' ? 'ok' : 'off' }}">{{ $pattern->status }}</span>
                <span class="pd-badge owner" title="Owner">{{ $pattern->owner_type === 'FOUNDRY' ? 'Foundry-owned' : 'Property of '.$pattern->ownerLabel() }}</span>
                <span class="pd-badge type" title="{{ \App\Models\Pattern::typeName($pattern->pattern_type) }}">{!! \App\Support\MouldIcons::patternType($pattern->pattern_type, 15) !!} {{ $typeLabel }}</span>
            </div>
            <div class="pd-meta">
                <span>Code: <strong>{{ $pattern->code }}</strong></span>
                @if ($pattern->mark_code)<span>Engraved stamp: <span class="stamp">{{ $pattern->mark_code }}</span></span>@endif
                @if ($madeProducts->isEmpty())<span>Makes: no product linked</span>@endif
            </div>
            @if ($madeProducts->isNotEmpty())
                @php($productPhotos = \App\Models\Attachment::coverUrlsFor('product', $madeProducts->pluck('id')->all()))
                <div class="pd-makes">
                    <span class="pd-makes-label">Makes</span>
                    @foreach ($madeProducts as $made)
                        <a class="pd-product" href="{{ route('products.show', $made) }}">
                            @isset($productPhotos[$made->id])<img src="{{ $productPhotos[$made->id] }}" alt="" loading="lazy">@else<span class="pd-noimg">{!! \App\Support\NavIcons::get('layers', 16) !!}</span>@endisset
                            <span><strong>{{ $made->name }}</strong><small>{{ $made->code }}</small></span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="pd-actions">
            <span class="pd-nav">
                @if ($previousPattern)<a class="btn" href="{{ route('patterns.show', array_filter(['pattern' => $previousPattern, 'link' => request('link')])) }}" title="Previous: {{ $previousPattern->code }} — {{ $previousPattern->name }}" aria-label="Previous pattern, {{ $previousPattern->code }}">&larr;</a>@else<span class="btn" aria-disabled="true" style="opacity:.4;cursor:default">&larr;</span>@endif
                @if ($nextPattern)<a class="btn" href="{{ route('patterns.show', array_filter(['pattern' => $nextPattern, 'link' => request('link')])) }}" title="Next: {{ $nextPattern->code }} — {{ $nextPattern->name }}" aria-label="Next pattern, {{ $nextPattern->code }}">&rarr;</a>@else<span class="btn" aria-disabled="true" style="opacity:.4;cursor:default">&rarr;</span>@endif
            </span>
            <a class="btn" href="{{ route('patterns.index') }}">All patterns</a>
            <a class="btn" href="{{ route('labels.show', ['pattern', $pattern->id]) }}" target="_blank">Print tag</a>
            @if ($pattern->trashed())
                <form method="POST" action="{{ route('patterns.restore', $pattern) }}" style="display:inline">@csrf<button class="btn btn-primary" type="submit">Restore pattern</button></form>
            @else
                <form method="POST" action="{{ route('patterns.archive', $pattern) }}" style="display:inline" onsubmit="return confirm(@js('Archive '.$pattern->code.'? It leaves the pattern list and pickers but stays on old runs, and can be restored.'))">@csrf @method('DELETE')<button class="btn" type="submit">Archive</button></form>
                <button type="button" class="btn btn-primary" data-quick="qd-revision">+ New revision</button>
            @endif
        </div>
    </div>
    <dl class="pd-tiles">
        <div class="pd-tile"><dt>Castings per mould</dt>
            <dd>{{ $current && $cavities ? $levels * $cavities : '—' }}</dd>
            <span class="sub link">@if ($current){{ $levels }} {{ $levels === 1 ? 'level' : 'levels' }} × {{ $cavities }} {{ $cavities === 1 ? 'cavity' : 'cavities' }}@if ($closedCavities) (+ {{ $closedCavities }} closed)@endif @else no layout yet @endif</span></div>
        <div class="pd-tile"><dt>Active revision</dt>
            <dd class="mono">{{ $current?->shortCode() ?? '—' }}</dd>
            <span class="sub {{ $current?->status === 'APPROVED' ? 'good' : '' }}">@if ($current){{ ucfirst(strtolower($current->status)) }} · Current @else Recorded with the first cavity map @endif</span></div>
        <div class="pd-tile"><dt>Default station &amp; sand</dt>
            <dd title="{{ $station ? $station->code.' — '.$station->name : '' }}">{{ $station?->code ?? 'Any station' }}</dd>
            <span class="sub">Sand: {{ $pattern->sand_system ?: ($station?->defaultSand() ? $station->defaultSand().' (station default)' : 'not recorded') }}</span></div>
        <div class="pd-tile"><dt>Flask size</dt>
            <dd>{{ $current?->isFlaskless() ? 'Flaskless' : ($flask?->name ?? '—') }}</dd>
            <span class="sub">@if ($current){!! \App\Support\MouldIcons::arrangement($current->mould_arrangement, 15) !!} {{ \Illuminate\Support\Str::before($current->arrangementLabel(), ' (') }}@if (in_array($current->mould_arrangement, ['STACK', 'CORE_PACKAGE'], true)) · {{ $sections }} sections @endif @else — @endif</span></div>
        <div class="pd-tile"><dt>Location</dt>
            <dd>{{ $pattern->custody_location ?: (\App\Models\Pattern::CUSTODIES[$pattern->custody] ?? 'Not recorded') }}</dd>
            <span class="sub">{{ $pattern->custody_location ? (\App\Models\Pattern::CUSTODIES[$pattern->custody] ?? '') : 'No storage place recorded' }}</span></div>
        <div class="pd-tile"><dt>Shakeout cooling</dt>
            <dd>{{ $cooling !== null ? $cooling.' min' : '—' }}</dd>
            <span class="sub {{ $cooling !== null ? 'good' : '' }}">{{ $cooling !== null ? 'Enforced before shakeout' : 'No minimum set' }}</span></div>
    </dl>
</div>
@if ($pattern->trashed() && in_array($pattern->custody, ['RETURNED', 'SCRAPPED'], true))<div class="alert alert-warning">{{ $pattern->custody === 'RETURNED' ? 'Returned to '.$pattern->ownerLabel() : 'Scrapped' }} on {{ $pattern->deleted_at->displayDate() }}. It cannot be moulded.@if ($pattern->custody === 'RETURNED') Restore it if the customer sends it back.@endif</div>
@elseif ($pattern->trashed())<div class="alert alert-warning">Archived on {{ $pattern->deleted_at->displayDate() }}. It is left out of the pattern list, its counts and the moulding pickers, and stays on the runs and tooling records that used it. Restore it to use it again.</div>@endif

@include('partials.quick-dialog', ['id' => 'qd-revision', 'title' => 'New revision of '.$pattern->code, 'action' => route('patterns.revisions.store', $pattern), 'submit' => 'Create revision', 'fields' => 'patterns._quick-revision', 'multipart' => true, 'current' => $current])

<div class="tab-marker" data-tab-start="Pattern dossier"></div>
<div class="pd-dossier">
<div class="pd-main">
@include('patterns._cavity-map', ['pattern' => $pattern, 'productRevisions' => $productRevisions])
</div>
<aside class="pd-side">
<div class="panel" data-inplace>
    <div class="panel-header"><h2>Physical tooling plate</h2>@if ($canEditInPlace)<button type="button" class="btn btn-small" data-inplace-open @if ($editingInPlace) hidden @endif>Edit specs</button>@endif</div>
    <div class="panel-body">
        @if ($canEditInPlace)
        <form method="POST" action="{{ route('patterns.update', $pattern) }}" data-inplace-form @unless ($editingInPlace) hidden @endunless>
            @csrf @method('PUT')<input type="hidden" name="_inplace" value="1">
            @if ($editingInPlace)<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
            <div class="form-grid">
                @include('patterns._tooling-fields', ['patternTypes' => array_keys(\App\Models\Pattern::TYPES), 'patternStatuses' => ['ACTIVE', 'INACTIVE', 'RETIRED']])
            </div>
            <div class="actions" style="margin-top:12px"><button class="btn btn-primary" type="submit">Save</button><button class="btn" type="button" data-inplace-cancel>Cancel</button></div>
        </form>
        @endif
        <div data-inplace-view @if ($editingInPlace) hidden @endif>
            <div class="tool-photo">@include('attachments._cover', ['type' => 'pattern', 'id' => $pattern->id, 'size' => 320])</div>
            <dl class="tool-facts">
                <div><dt>Material</dt><dd>{{ $pattern->material ?: '—' }}</dd></div>
                <div><dt>Dimensions (L × W × H)</dt><dd>@if ($pattern->length_mm || $pattern->width_mm || $pattern->height_mm){{ $mm($pattern->length_mm) }} × {{ $mm($pattern->width_mm) }} × {{ $mm($pattern->height_mm) }} mm @else — @endif</dd></div>
                <div><dt>Made for sand</dt><dd>{{ $pattern->sand_system ?: ($station?->defaultSand() ? $station->defaultSand() : 'Not recorded') }}@if (! $pattern->sand_system && $station?->defaultSand())<span class="sub">station default</span>@endif @if ($pattern->compatible_sands)<span class="sub">also works on {{ implode(', ', $pattern->compatible_sands) }}</span>@endif</dd></div>
                <div><dt>Current revision</dt><dd>@if ($current)<span class="mono">{{ $current->code }}</span>@if ($current->drawing_revision)<span class="sub">drawing rev {{ $current->drawing_revision }}</span>@endif @else — @endif</dd></div>
                <div class="fact-wide"><dt>Engraved stamp</dt><dd>@include('tooling::tools._mark', ['tool' => $pattern, 'type' => 'pattern', 'bare' => true, 'optional' => true])</dd></div>
            </dl>
            @if ($pattern->description || $pattern->notes)
                <div class="tool-notes">
                    @if ($pattern->description)<div><h3>Description</h3><p>{{ $pattern->description }}</p></div>@endif
                    @if ($pattern->notes)<div><h3>General tooling notes</h3><p>{{ $pattern->notes }}</p></div>@endif
                </div>
            @endif
        </div>
    </div>
</div>
@if ($canEditInPlace)
<script>
document.querySelectorAll('[data-inplace]').forEach(function (panel) {
    var form = panel.querySelector('[data-inplace-form]'), view = panel.querySelectorAll('[data-inplace-view]'), open = panel.querySelector('[data-inplace-open]');
    function show(editing) {
        form.hidden = !editing; open.hidden = editing;
        view.forEach(function (el) { el.hidden = editing; });
        if (editing) { var first = form.querySelector('input:not([type=hidden])'); if (first) first.focus(); } else { form.reset(); }
    }
    open.addEventListener('click', function () { show(true); });
    form.querySelector('[data-inplace-cancel]').addEventListener('click', function () { show(false); });
});
</script>
@endif

@if (app(\App\Domains\Core\ModuleRegistry::class)->isEnabled('tooling'))
@include('patterns._tool-life', ['pattern' => $pattern, 'toolLife' => $toolLife])
@endif

<div class="panel"><div class="panel-header"><h2>Sand system &amp; line match</h2></div><div class="panel-body">
    <p class="muted small" style="margin-top:0">A pattern is made with the taper and allowances of one sand system. The work list warns when it is put on a line running a sand system that is not listed here.</p>
    <form method="POST" action="{{ route('patterns.sand', $pattern) }}" class="form-grid">@csrf @method('PUT')
        @include('patterns._sand', ['sands' => \App\Models\SandSystem::choices($pattern->sand_system, ...($pattern->compatible_sands ?? []))])
        <div><button class="btn btn-primary">Save sand system</button></div>
    </form>
</div></div>

@include('patterns._cooling', ['pattern' => $pattern])
</aside>
</div>

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

<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'pattern', 'id' => $pattern->id])
@include('attachments._panel', ['type' => 'pattern', 'id' => $pattern->id])
@include('patterns._history', ['pattern' => $pattern])
@endsection
