
@extends('layouts.app')

@section('title', $product->code)
@section('breadcrumb')
<a href="{{ route('products.index') }}">Products</a> / {{ $product->code }}
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h1>{{ $product->name }}</h1>
        <p>{{ $product->code }} · {{ $product->product_type }}</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('products.index') }}">All products</a>
        @if ($product->trashed())
            <form method="POST" action="{{ route('products.restore', $product) }}" style="display:inline">@csrf<button class="btn btn-primary" type="submit">Restore product</button></form>
        @else
            <form method="POST" action="{{ route('products.archive', $product) }}" style="display:inline" onsubmit="return confirm(@js('Archive '.$product->code.'? It leaves the product list and pickers but stays on old orders, and can be restored.'))">@csrf @method('DELETE')<button class="btn" type="submit">Archive</button></form>
            <a class="btn btn-primary" href="{{ route('products.edit', $product) }}">Edit product</a>
        @endif
    </div>
</div>
@if ($product->trashed())<div class="alert alert-warning">Archived on {{ $product->deleted_at->displayDate() }}. It is left out of the product list, its counts and every product picker, and stays on the orders and lots that used it. Restore it to use it again.</div>@endif



@unless ($product->trashed())
@include('partials.search-select')
@include('partials.quick-dialog', ['id' => 'qd-link-pattern', 'title' => 'Link a pattern to '.$product->code, 'action' => route('products.link-pattern', $product), 'submit' => 'Link pattern', 'fields' => 'products._link-pattern-fields'])
@endunless
<div class="tab-marker" data-tab-start="Specification"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Product specification</h2>
        <span class="status {{ $product->active ? 'status-completed' : 'status-cancelled' }}">
            {{ $product->active ? 'ACTIVE' : 'INACTIVE' }}
        </span>
    </div>
    <div class="panel-body">
        @include('attachments._cover', ['type' => 'product', 'id' => $product->id])
        <div style="margin:6px 0 10px">{!! \App\Support\Code128::svg($product->code, 40, 2) !!} <a class="small" href="{{ route('labels.show', ['product', $product->id]) }}" target="_blank">Print label →</a></div>
        <div class="detail-grid">
            <div>
                <div class="detail-label">Product code</div>
                <div class="detail-value">{{ $product->code }}</div>
            </div>
            <div>
                <div class="detail-label">Product name</div>
                <div class="detail-value">{{ $product->name }}</div>
            </div>
            <div>
                <div class="detail-label">Product type</div>
                <div class="detail-value">{{ $product->product_type }}</div>
            </div>
            <div>
                <div class="detail-label">Base unit</div>
                <div class="detail-value">{{ $product->base_uom }}</div>
            </div>
            <div>
                <div class="detail-label">Nominal weight</div>
                <div class="detail-value">
                    {{ $product->nominal_weight_kg !== null
                        ? \App\Support\Formats::number((float) $product->nominal_weight_kg, 4).' kg'
                        : 'Not specified' }}
                </div>
            </div>
            <div>
                <div class="detail-label">Revision count</div>
                <div class="detail-value">{{ $product->revisions->count() }}</div>
            </div>
            @php
                $currentPatterns = $product->revisions->flatMap->patternCavities->map->patternRevision
                    ->filter(fn ($r) => $r->is_current && ! $r->pattern->trashed())->unique('pattern_id')->map->pattern->sortBy('name');
            @endphp
            <div>
                <div class="detail-label">Patterns</div>
                <div class="detail-value">
                    @forelse ($currentPatterns as $p)<a href="{{ route('patterns.show', $p) }}">{{ $p->name }}</a> <span class="muted small mono">{{ $p->code }}</span>@if (! $loop->last)<br>@endif
                    @empty<span class="muted">None linked yet</span> @unless ($product->trashed())<a href="#" class="qd-add" data-quick="qd-link-pattern">Link a pattern</a>@endunless @endforelse
                </div>
            </div>
        </div>

        @if ($product->description)
            <div style="margin-top:20px">
                <div class="detail-label">Description</div>
                <div>{{ $product->description }}</div>
            </div>
        @endif
    </div>
</div>
<div class="tab-marker" data-tab-start="Iron grade"></div>
@php
    $planner = app(\App\Services\Planning\HeatPlanner::class);
    $currentGrades = $product->revisions->mapWithKeys(fn ($r) => [$r->id => $planner->gradeFor($r)]);
    $latest = $product->revisions->first();
@endphp
<div class="panel">
    <div class="panel-header"><h2>Iron grade</h2></div>
    <div class="panel-body">
        @if ($product->revisions->isEmpty())
            <p class="muted">Add a revision first.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Revision</th><th>Current grade</th><th>Type</th></tr></thead>
                <tbody>@foreach ($product->revisions as $revision)
                    @php $current = $currentGrades[$revision->id]; @endphp
                    <tr><td>{{ $revision->revision }} <span class="muted small">{{ $revision->status }}</span></td><td>{!! $current ? '<strong>'.e($current->code).'</strong>' : '<span class="muted">Not set</span>' !!}</td><td>{{ $current?->familyLabel() ?? '—' }}</td></tr>
                @endforeach</tbody>
            </table></div>
            <form method="POST" action="{{ route('products.grade', $product) }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-top:12px">@csrf
                <div class="field"><label for="grade_revision">Revision</label>
                    <select id="grade_revision" name="product_revision_id" data-search-select>@foreach ($product->revisions as $revision)<option value="{{ $revision->id }}" data-sub="{{ $revision->status }}{{ $currentGrades[$revision->id] ? ' · '.$currentGrades[$revision->id]->code : '' }}" @selected($revision->id === $latest->id)>{{ $revision->revision }}</option>@endforeach</select></div>
                <div class="field"><label for="grade_id">Iron grade</label>
                    <select id="grade_id" name="iron_grade_id" data-search-select>@foreach (\App\Models\IronGrade::listed() as $g)<option value="{{ $g->id }}" data-sub="{{ $g->familyLabel() }}" @selected($currentGrades[$latest->id]?->id === $g->id)>{{ $g->code }}</option>@endforeach</select></div>
                <button class="btn btn-primary" type="submit">Set grade</button>
            </form>
            @include('partials.search-select')
            <p class="muted small">Records an approved specification naming the grade. The contract review and the melting plan read it.</p>
        @endif
    </div>
</div>

<div class="tab-marker" data-tab-start="Specification"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Product specification</h2>
        <span class="status {{ $product->active ? 'status-completed' : 'status-cancelled' }}">
            {{ $product->active ? 'ACTIVE' : 'INACTIVE' }}
        </span>
    </div>
    <div class="panel-body">
        @include('attachments._cover', ['type' => 'product', 'id' => $product->id])
        <div style="margin:6px 0 10px">{!! \App\Support\Code128::svg($product->code, 40, 2) !!} <a class="small" href="{{ route('labels.show', ['product', $product->id]) }}" target="_blank">Print label →</a></div>
        <div class="detail-grid">
            <div>
                <div class="detail-label">Product code</div>
                <div class="detail-value">{{ $product->code }}</div>
            </div>
            <div>
                <div class="detail-label">Product name</div>
                <div class="detail-value">{{ $product->name }}</div>
            </div>
            <div>
                <div class="detail-label">Product type</div>
                <div class="detail-value">{{ $product->product_type }}</div>
            </div>
            <div>
                <div class="detail-label">Base unit</div>
                <div class="detail-value">{{ $product->base_uom }}</div>
            </div>
            <div>
                <div class="detail-label">Nominal weight</div>
                <div class="detail-value">
                    {{ $product->nominal_weight_kg !== null
                        ? \App\Support\Formats::number((float) $product->nominal_weight_kg, 4).' kg'
                        : 'Not specified' }}
                </div>
            </div>
            <div>
                <div class="detail-label">Revision count</div>
                <div class="detail-value">{{ $product->revisions->count() }}</div>
            </div>
            @php
                $currentPatterns = $product->revisions->flatMap->patternCavities->map->patternRevision
                    ->filter(fn ($r) => $r->is_current && ! $r->pattern->trashed())->unique('pattern_id')->map->pattern->sortBy('name');
            @endphp
            <div>
                <div class="detail-label">Patterns</div>
                <div class="detail-value">
                    @forelse ($currentPatterns as $p)<a href="{{ route('patterns.show', $p) }}">{{ $p->name }}</a> <span class="muted small mono">{{ $p->code }}</span>@if (! $loop->last)<br>@endif
                    @empty<span class="muted">None linked yet</span> @unless ($product->trashed())<a href="#" class="qd-add" data-quick="qd-link-pattern">Link a pattern</a>@endunless @endforelse
                </div>
            </div>
        </div>

        @if ($product->description)
            <div style="margin-top:20px">
                <div class="detail-label">Description</div>
                <div>{{ $product->description }}</div>
            </div>
        @endif
    </div>
</div>

<div class="tab-marker" data-tab-start="Revisions"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Revision history</h2>
    </div>

    @if ($product->revisions->isEmpty())
        <div class="empty-state">No revisions have been recorded for this product.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Revision</th>
                        <th>Status</th>
                        <th>Net weight</th>
                        <th>Effective from</th>
                        <th>Effective to</th>
                        <th>Engineering notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($product->revisions as $revision)
                        <tr>
                            <td><strong>{{ $revision->revision }}</strong></td>
                            <td><span class="status">{{ $revision->status }}</span></td>
                            <td>
                                {{ $revision->net_weight_kg !== null
                                    ? \App\Support\Formats::number((float) $revision->net_weight_kg, 4).' kg'
                                    : '—' }}
                            </td>
                            <td>{{ $revision->effective_from?->displayDate() ?? '—' }}</td>
                            <td>{{ $revision->effective_to?->displayDate() ?? '—' }}</td>
                            <td style="white-space:normal;min-width:220px">
                                {{ $revision->engineering_notes ?: '—' }}
                            </td>
                            <td>@unless ($product->trashed())<button type="button" class="rev-edit" data-rev-edit="{{ $revision->id }}">Edit</button>@endunless</td>
                        </tr>
                        @unless ($product->trashed())
                        <tr data-rev-form="{{ $revision->id }}" @unless ($errors->has('net_weight_kg') && old('_revision') === $revision->id) hidden @endunless>
                            <td colspan="7">
                                <form method="POST" action="{{ route('products.revisions.update', [$product, $revision]) }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">@csrf @method('PUT')<input type="hidden" name="_revision" value="{{ $revision->id }}">
                                    <div class="field"><label>Net weight (kg) of revision {{ $revision->revision }}</label><input name="net_weight_kg" type="number" step="0.0001" min="0.0001" value="{{ old('_revision') === $revision->id ? old('net_weight_kg') : ($revision->net_weight_kg !== null ? rtrim(rtrim((string) $revision->net_weight_kg, '0'), '.') : '') }}" style="width:150px">
                                        @if (old('_revision') === $revision->id)@error('net_weight_kg')<div class="error-text">{{ $message }}</div>@enderror @endif</div>
                                    <div class="field" style="flex:1;min-width:240px"><label>Engineering notes</label><input name="engineering_notes" maxlength="5000" value="{{ old('_revision') === $revision->id ? old('engineering_notes') : $revision->engineering_notes }}"></div>
                                    <button class="btn btn-primary" type="submit">Save revision</button><button class="btn" type="button" data-rev-cancel="{{ $revision->id }}">Cancel</button>
                                </form>
                            </td>
                        </tr>
                        @endunless
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>


<div class="panel">
    <div class="panel-header"><h2>Add revision</h2></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('products.revisions.store', $product) }}">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label for="revision">Revision identifier <span class="required">*</span></label>
                    <input id="revision" name="revision" maxlength="20" required
                           value="{{ old('revision', \App\Models\ProductRevision::nextLabel($product->revisions->pluck('revision'))) }}" placeholder="e.g. 1">
                </div>

                <div class="field">
                    <label for="status">Revision status <span class="required">*</span></label>
                    <select id="status" name="status" required>
                        @foreach (['DRAFT', 'APPROVED', 'OBSOLETE'] as $status)
                            <option value="{{ $status }}" @selected(old('status', 'DRAFT') === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="net_weight_kg">Net weight (kg)</label>
                    <input id="net_weight_kg" name="net_weight_kg" type="number"
                           step="0.0001" min="0" value="{{ old('net_weight_kg') }}">
                </div>

                <div class="field">
                    <label for="effective_from">Effective from</label>
                    <input id="effective_from" name="effective_from" type="date"
                           value="{{ old('effective_from') }}">
                </div>

                <div class="field">
                    <label for="effective_to">Effective to</label>
                    <input id="effective_to" name="effective_to" type="date"
                           value="{{ old('effective_to') }}">
                </div>

                <div class="field field-full">
                    <label for="engineering_notes">Engineering notes</label>
                    <textarea id="engineering_notes" name="engineering_notes">{{ old('engineering_notes') }}</textarea>
                </div>
            </div>

            <div class="actions" style="margin-top:18px">
                <button class="btn btn-primary" type="submit">Add revision</button>
            </div>
        </form>
    </div>
</div>
@include('control-plans._panel', ['product' => $product])
<div class="tab-marker" data-tab-start="Patterns"></div>
@include('products._made-on-patterns', ['product' => $product])

<div class="tab-marker" data-tab-start="Casting analysis"></div>
@php($analyses = \App\Models\CastingAnalysis::where('product_id', $product->id)->orderByDesc('created_at')->limit(10)->get())
<div class="panel">
    <div class="panel-header">
        <h2>Casting analysis</h2>
        <a class="btn btn-primary btn-small" href="{{ route('casting-analyses.create', ['product' => $product->id]) }}">+ Analyse a model</a>
    </div>
    @if ($analyses->isEmpty())
        <div class="panel-body muted">No analysis yet. Attach an STL or STEP model on the Files tab, or upload one here, to find hotspots, size feeders and get a manufacturability score.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Code</th><th>Name</th><th>Score</th><th>Status</th><th>When</th><th></th></tr></thead>
            <tbody>@foreach ($analyses as $an)
                <tr><td><strong>{{ $an->code }}</strong></td><td>{{ $an->name }}</td><td>{{ $an->score !== null ? $an->score.' ('.$an->score_letter.')' : '—' }}</td>
                    <td><span class="status {{ $an->status === 'COMPLETE' ? 'status-completed' : 'status-cancelled' }}">{{ $an->status }}</span></td>
                    <td class="muted small">{{ $an->created_at->diffForHumans() }}</td><td><a href="{{ route('casting-analyses.show', $an) }}">Open →</a></td></tr>
            @endforeach</tbody>
        </table></div>
    @endif
</div>
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'product', 'id' => $product->id])
@include('attachments._panel', ['type' => 'product', 'id' => $product->id])
@include('audit._history', ['type' => 'Product', 'id' => $product->id])
<style>
    .rev-edit { appearance: none; border: 0; background: transparent; padding: 0; font: inherit; font-size: 12px; font-weight: 600; color: var(--accent); cursor: pointer; }
    .rev-edit:hover { text-decoration: underline; }
</style>
<script>
document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-rev-edit]'), cancel = e.target.closest('[data-rev-cancel]');
    if (!open && !cancel) return;
    var id = (open || cancel).dataset.revEdit || (open || cancel).dataset.revCancel, row = document.querySelector('[data-rev-form="' + id + '"]');
    if (!row) return;
    row.hidden = !open;
    if (open) { var input = row.querySelector('input[name="net_weight_kg"]'); if (input) input.focus(); }
});
</script>
@endsection
