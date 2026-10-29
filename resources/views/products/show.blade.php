
@extends('layouts.app')

@section('title', $product->code)
@section('breadcrumb')
<a href="{{ route('products.index') }}">Products</a> / {{ $product->code }}
@endsection

@section('content')
<div class="pd-bar">
    <div class="pd-bar-left">
        <a href="{{ route('products.index') }}" class="pd-crumb">Products</a>
        <span class="muted">/</span>
        <span class="pd-bar-title" title="{{ $product->code }} {{ $product->name }}">{{ $product->code }} {{ $product->name }}</span>
        <span class="pd-edit-only muted">/</span><span class="pd-edit-only">Edit</span>
        <span class="pd-tabs-slot"></span>
    </div>
    <div class="pd-actions">
        @if ($product->trashed())
            <form method="POST" action="{{ route('products.restore', $product) }}" style="display:inline">@csrf<button class="pd-btn primary" type="submit">Restore product</button></form>
        @else
            <span class="pd-view-only pd-actions">
                <form method="POST" action="{{ route('products.archive', $product) }}" style="display:inline" onsubmit="return confirm(@js('Archive '.$product->code.'? It leaves the product list and pickers but stays on old orders, and can be restored.'))">@csrf @method('DELETE')<button class="pd-btn" type="submit">Archive</button></form>
                <button class="pd-btn" type="button" data-new-revision>New revision</button>
                <a class="pd-btn primary" href="{{ route('products.show', $product) }}?edit=1" data-edit-product>Edit product</a>
            </span>
            <span class="pd-edit-only pd-actions">
                <button class="pd-btn" type="button" data-edit-cancel>Cancel</button>
                <button class="pd-btn" type="submit" form="pd-edit-form" name="save_as" value="draft">Save as rev {{ \App\Models\ProductRevision::nextLabel($product->revisions->pluck('revision')) }} draft</button>
                <button class="pd-btn primary" type="submit" form="pd-edit-form" name="save_as" value="current">Save changes</button>
            </span>
        @endif
    </div>
</div>
@if ($product->trashed())<div class="alert alert-warning">Archived on {{ $product->deleted_at->displayDate() }}. It is left out of the product list, its counts and every product picker, and stays on the orders and lots that used it. Restore it to use it again.</div>@endif



@unless ($product->trashed())
@include('partials.search-select')
@include('partials.quick-dialog', ['id' => 'qd-link-pattern', 'title' => 'Link a pattern to '.$product->code, 'action' => route('products.link-pattern', $product), 'submit' => 'Link pattern', 'fields' => 'products._link-pattern-fields'])
@include('partials.quick-dialog', ['id' => 'qd-new-pattern', 'title' => 'New pattern', 'action' => route('patterns.quick'), 'submit' => 'Add pattern', 'fields' => 'products._new-pattern-fields'])
@endunless
<div class="tab-marker" data-tab-start="Overview"></div>
<div class="pd-view-only">@include('products._overview', ['product' => $product, 'facts' => $facts])</div>
@unless ($product->trashed())<div class="pd-edit-only">@include('products._overview-edit', ['product' => $product, 'facts' => $facts])</div>@endunless
<div class="tab-marker" data-tab-start="Revisions"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Revision history</h2>
        @unless ($product->trashed())<button class="btn btn-small" type="button" data-rev-add aria-controls="add-revision">Add revision</button>@endunless
    </div>
    <div class="panel-body" id="add-revision" @unless ($errors->hasAny(['revision', 'status', 'net_weight_kg', 'effective_from', 'effective_to', 'engineering_notes'])) hidden @endunless>
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
                           step="any" min="0" value="{{ old('net_weight_kg') }}">
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
                <button class="btn" type="button" data-rev-add-cancel>Cancel</button>
            </div>
        </form>
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
                                    <div class="field"><label>Net weight (kg) of revision {{ $revision->revision }}</label><input name="net_weight_kg" type="number" step="any" min="0" value="{{ old('_revision') === $revision->id ? old('net_weight_kg') : ($revision->net_weight_kg !== null ? rtrim(rtrim((string) $revision->net_weight_kg, '0'), '.') : '') }}" style="width:150px">
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


<div class="tab-marker" data-tab-start="BOM"></div>
@include('products._bom', ['product' => $product, 'facts' => $facts])
@foreach (app(\App\Domains\Core\ModuleRegistry::class)->panels('product.show') as $panel)@if (str_starts_with($panel, 'engineering::'))@include($panel, ['product' => $product])@endif @endforeach
<div class="tab-marker" data-tab-start="Routing"></div>
<livewire:product-route :product="$product" />
@unless (app(\App\Services\Production\ProcessRoutes::class)->productRoute($product))@include('products._extra-steps', ['product' => $product])@endunless
@include('control-plans._panel', ['product' => $product])
@foreach (app(\App\Domains\Core\ModuleRegistry::class)->panels('product.show') as $panel)@unless (str_starts_with($panel, 'engineering::'))@include($panel, ['product' => $product])@endunless@endforeach
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
                    <tr><td>{{ $revision->revision }} <span class="status">{{ $revision->status }}</span></td><td>{!! $current ? '<strong>'.e($current->code).'</strong>' : '<span class="muted">Not set</span>' !!}</td><td>{{ $current?->familyLabel() ?? '—' }}</td></tr>
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
    .pd-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px 16px; flex-wrap: wrap; background: #fff; border: 1px solid #e6e7e9; border-radius: 10px 10px 0 0; padding: 14px 28px; font-size: 13px; }
    .pd-bar-left { display: flex; align-items: center; gap: 16px; flex-wrap: nowrap; flex: 1 1 100%; min-width: 0; }
    .pd-crumb { font-weight: 600; color: #1c1d1f; text-decoration: none; flex: none; }
    .pd-bar-left > span { white-space: nowrap; }
    .pd-bar-title { flex: 0 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
    .pd-bar .tabbar { position: static; border: 0; margin: 0; padding: 0 0 2px; background: none; gap: 4px; flex: 1 1 auto; min-width: 0; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; scrollbar-width: thin; }
    .pd-bar .tabbar button { padding: 4px 10px; margin: 0; border: 0; border-radius: 6px; font-size: 13px; font-weight: 400; color: #6b6e73; flex: none; white-space: nowrap; }
    .pd-bar .tabbar button:hover { background: #f0f1f3; color: #1c1d1f; }
    .pd-bar .tabbar button[aria-selected="true"] { background: #f0f1f3; color: #1c1d1f; }
    .pd-actions { display: flex; gap: 8px; align-items: center; justify-content: flex-end; margin-left: auto; flex: none; }
    .pd-actions form { display: inline; }
    .pd-btn { padding: 7px 14px; border-radius: 6px; background: #fff; border: 1px solid #d9dbde; color: #1c1d1f; font: inherit; font-size: 13px; text-decoration: none; cursor: pointer; }
    .pd-btn.primary { background: #2f5fd0; border-color: #2f5fd0; color: #fff; font-weight: 500; }
    .pd-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; background: #fff; border: 1px solid #e6e7e9; border-top: 0; border-radius: 0 0 10px 10px; font-size: 13px; color: #1c1d1f; margin-bottom: 18px; }
    .pd-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 22px; min-width: 0; }
    .pd-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .pd-facts .k { font-size: 12px; color: #6b6e73; }
    .pd-cols { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 32px; }
    .pd-h { font-size: 12px; font-weight: 600; color: #6b6e73; text-transform: uppercase; letter-spacing: .06em; }
    .pd-list { display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; margin: 10px 0 0; }
    .pd-list dt { color: #6b6e73; }
    .pd-list dd { margin: 0; text-align: right; }
    .pd-grid .mono { font-family: ui-monospace, Menlo, monospace; font-size: 12.5px; }
    .pd-table { width: 100%; border-collapse: collapse; }
    .pd-table th { text-align: left; font-size: 12px; font-weight: 500; color: #6b6e73; padding: 8px 12px; border-bottom: 1px solid #e6e7e9; background: none; text-transform: none; letter-spacing: 0; color: #6b6e73; }
    .pd-table td { padding: 10px 12px; border-bottom: 1px solid #e6e7e9; }
    .pd-table tbody tr:last-child td { border-bottom: 0; }
    .pd-table .r { text-align: right; }
    .pd-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; background: #f0f1f3; color: #55585d; }
    .pd-pill.ok { background: #e7f4ec; color: #1d7048; }
    .pd-pill.info { background: #eaf0fd; color: #2f5fd0; }
    .pd-pill.warn { background: #fbf0dc; color: #94610a; }
    .pd-side { border-left: 1px solid #e6e7e9; padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 14px; border-radius: 0 0 10px 0; }
    .pd-row { display: flex; justify-content: space-between; gap: 12px; }
    .pd-row span:first-child { color: #6b6e73; }
    .pd-row span:last-child { text-align: right; }
    .pd-side hr { border: 0; border-top: 1px solid #e6e7e9; margin: 2px 0; width: 100%; }
    .pd-note { margin: 0; color: #55585d; }
    .pd-callout { padding: 12px 14px; border: 1px solid #cdd9f5; border-radius: 8px; background: #fff; color: #2f5fd0; }
    @media (max-width: 1100px) { .pd-grid { grid-template-columns: 1fr; } .pd-side { border-left: 0; border-top: 1px solid #e6e7e9; } .pd-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); } .pd-cols { grid-template-columns: 1fr; } .pd-bar { padding-left: 16px; padding-right: 16px; } }
    body:not(.pd-editing) .pd-edit-only, body.pd-editing .pd-view-only { display: none !important; }
    .pe-main { padding: 4px 40px 12px; display: flex; flex-direction: column; min-width: 0; }
    .pe-sec { display: grid; grid-template-columns: 200px minmax(0, 1fr); gap: 32px; padding: 24px 0; border-bottom: 1px solid #e6e7e9; }
    .pe-head { display: flex; flex-direction: column; gap: 4px; }
    .pe-title { font-size: 15px; font-weight: 600; color: #1c1d1f; }
    .pe-desc { font-size: 13px; color: #6b6e73; line-height: 1.5; text-wrap: pretty; }
    .pe-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 20px; align-content: start; }
    .pe-f { display: flex; flex-direction: column; gap: 6px; font-size: 13px; color: #6b6e73; margin: 0; font-weight: 400; }
    .pe-l { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; color: #3d4045; }
    .pe-tag { font-style: normal; font-size: 11px; font-weight: 500; color: #2f5fd0; background: #eaf0fd; padding: 1px 6px; border-radius: 4px; }
    .pe-f input, .pe-f select, .pe-f textarea { height: 38px; padding: 0 12px; border: 1px solid #d9dbde; border-radius: 6px; font-size: 14px; background: #fff; color: #1c1d1f; font-family: inherit; width: 100%; box-sizing: border-box; margin: 0; }
    .pe-f select { padding: 0 8px; }
    .pe-f textarea { height: auto; padding: 10px 12px; line-height: 1.5; min-height: 80px; resize: vertical; }
    .pe-f.pe-edited input, .pe-f.pe-edited select { border-color: #2f5fd0; box-shadow: 0 0 0 3px #eaf0fd; }
    .pe-was { font-size: 12px; color: #2f5fd0; }
    .pe-f .mono { font-family: ui-monospace, Menlo, monospace; font-size: 13.5px; }
    .pe-f .ro, .pe-f input:disabled { background: #f8f8f9; color: #6b6e73; }
    .pe-help { font-size: 11.5px; color: #8a8d92; }
    .pe-changes > div { display: flex; flex-direction: column; gap: 2px; padding: 8px 0; border-bottom: 1px solid #e6e7e9; }
    .pe-changes .k { font-size: 12px; color: #6b6e73; }
    .pe-changes s { color: #8a8d92; }
    .pe-changes .pe-none { color: #8a8d92; border: 0; }
    .pe-callout-text { font-size: 12.5px; color: #55585d; line-height: 1.5; margin-top: 4px; }
    .pe-more summary { cursor: pointer; font-size: 12px; font-weight: 600; color: #6b6e73; text-transform: uppercase; letter-spacing: .06em; }
    @media (max-width: 1100px) { .pe-sec { grid-template-columns: 1fr; gap: 14px; } .pe-main { padding: 4px 16px 12px; } }
    .rev-edit { appearance: none; border: 0; background: transparent; padding: 0; font: inherit; font-size: 12px; font-weight: 600; color: var(--accent); cursor: pointer; }
    .rev-edit:hover { text-decoration: underline; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var bar = document.querySelector('.tabbar'), slot = document.querySelector('.pd-tabs-slot');
    if (bar && slot) { slot.replaceWith(bar); }
    if (@json(($editing ?? false) || old('_pd_edit') !== null)) { pdEdit(true); }
});
// Edit product (design 10g) turns the overview tab into its form in place; Cancel puts back what was there.
function pdEdit(on) {
    var form = document.getElementById('pd-edit-form');
    if (!form) { return; }
    document.body.classList.toggle('pd-editing', on);
    if (on) {
        var tab = Array.prototype.find.call(document.querySelectorAll('.tabbar button'), function (b) { return b.textContent.indexOf('Overview') === 0; });
        if (tab) { tab.click(); }
        pdChanges();
    }
}
function pdChanges() {
    var form = document.getElementById('pd-edit-form');
    if (!form) { return; }
    var shown = function (el, original) {
        if (el.tagName === 'SELECT') { var o = Array.prototype.find.call(el.options, function (x) { return original ? x.defaultSelected : x.selected; }) || el.options[0]; return o && o.value !== '' ? o.text : 'not set'; }
        var v = original ? el.defaultValue : el.value;
        return v === '' ? 'not set' : v + (el.dataset.unit ? ' ' + el.dataset.unit : '');
    };
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
    var rows = [], method = [];
    form.querySelectorAll('[data-label]').forEach(function (el) {
        var changed = el.tagName === 'SELECT' ? Array.prototype.some.call(el.options, function (o) { return o.selected !== o.defaultSelected; }) : el.value !== el.defaultValue;
        var label = el.closest('.pe-f'), was = label && label.querySelector('.pe-was');
        if (label) { label.classList.toggle('pe-edited', changed); }
        if (!changed) { if (was) { was.remove(); } return; }
        if (label && !el.hasAttribute('data-poured')) { if (!was) { was = document.createElement('span'); was.className = 'pe-was'; label.appendChild(was); } was.textContent = 'Was ' + shown(el, true); }
        rows.push('<div><span class="k">' + esc(el.dataset.label) + '</span><span><s>' + esc(shown(el, true)) + '</s> → ' + esc(shown(el, false)) + '</span></div>');
        if (el.hasAttribute('data-method')) { method.push(el.dataset.label); }
    });
    form.querySelector('[data-changes]').innerHTML = rows.length ? rows.join('') : '<div class="pe-none">Nothing changed yet.</div>';
    var callout = form.querySelector('[data-needs-revision]');
    callout.hidden = method.length === 0;
    if (method.length) { form.querySelector('[data-method-names]').textContent = method.join(', ').replace(/^./, function (c) { return c.toUpperCase(); }); }

    var fx = JSON.parse(form.dataset.effect), poured = parseFloat(form.querySelector('[data-poured]').value), cast = parseFloat(form.querySelector('[data-cast]').value);
    var money = function (v) { return fx.currency.replace('#', v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })); };
    var cost = form.querySelector('[data-effect-cost]'), metal = form.querySelector('[data-effect-metal]'), yieldNote = form.querySelector('[data-yield]');
    var pouredChanged = fx.pouredKg && poured > 0 && Math.abs(poured - fx.pouredKg) > 1e-9;
    if (fx.materialCost !== null) {
        var now = fx.materialCost + (pouredChanged ? fx.perKgPoured * (poured - fx.pouredKg) : 0);
        cost.textContent = pouredChanged && fx.perKgPoured ? money(fx.materialCost) + ' → ' + money(now) : money(fx.materialCost);
    }
    if (metal && fx.openOrder && fx.pouredKg) {
        var t = function (kg) { return (kg * fx.openOrder.qty / 1000).toFixed(1); };
        metal.textContent = pouredChanged ? t(fx.pouredKg) + ' → ' + t(poured) + ' t' : t(fx.pouredKg) + ' t';
    }
    if (yieldNote && poured > 0 && cast > 0) {
        var was = fx.pouredKg && parseFloat(form.querySelector('[data-cast]').defaultValue) > 0 ? Math.round(100 * parseFloat(form.querySelector('[data-cast]').defaultValue) / fx.pouredKg) : null, y = Math.round(100 * cast / poured);
        yieldNote.textContent = (pouredChanged ? 'Was ' + fx.pouredKg + ' · yield ' : 'Yield ') + y + '%' + (was !== null && was !== y ? ', was ' + was + '%' : '');
    }
}
document.addEventListener('input', function (e) { if (e.target.closest('#pd-edit-form')) { pdChanges(); } });
document.addEventListener('change', function (e) { if (e.target.closest('#pd-edit-form')) { pdChanges(); } });
document.addEventListener('click', function (e) {
    if (e.target.closest('[data-edit-product]') && document.getElementById('pd-edit-form')) { e.preventDefault(); pdEdit(true); return; }
    if (e.target.closest('[data-edit-cancel]')) { document.getElementById('pd-edit-form').reset(); pdEdit(false); return; }
    if (e.target.closest('[data-new-revision]')) {
        var tab = Array.prototype.find.call(document.querySelectorAll('.tabbar button'), function (b) { return b.textContent.indexOf('Revisions') === 0; });
        if (tab) { tab.click(); }
        var add = document.querySelector('[data-rev-add]');
        if (add) { add.click(); }
        return;
    }
    var add = document.getElementById('add-revision');
    if (add && e.target.closest('[data-rev-add]')) { add.hidden = false; var first = add.querySelector('input[name="revision"]'); if (first) first.focus(); return; }
    if (add && e.target.closest('[data-rev-add-cancel]')) { add.hidden = true; return; }
    var open = e.target.closest('[data-rev-edit]'), cancel = e.target.closest('[data-rev-cancel]');
    if (!open && !cancel) return;
    var id = (open || cancel).dataset.revEdit || (open || cancel).dataset.revCancel, row = document.querySelector('[data-rev-form="' + id + '"]');
    if (!row) return;
    row.hidden = !open;
    if (open) { var input = row.querySelector('input[name="net_weight_kg"]'); if (input) input.focus(); }
});
</script>
@endsection
