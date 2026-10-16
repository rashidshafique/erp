@extends('layouts.app')

@section('title', $pattern->code)
@section('breadcrumb', 'Patterns / ' . $pattern->code)

@section('content')
<div class="page-heading">
    <div>
        <h1>{{ $pattern->code }}</h1>
        <p>{{ $pattern->name }}</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('patterns.index') }}">All patterns</a>
        <a class="btn btn-primary" href="#new-revision">+ Add revision</a>
    </div>
</div>

<div class="tab-marker" data-tab-start="Tooling"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Physical tooling record</h2>
        <span class="status {{ $pattern->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled' }}">
            {{ $pattern->status }}
        </span>
    </div>
    <div class="panel-body">
        @include('attachments._cover', ['type' => 'pattern', 'id' => $pattern->id])
        @include('tools._mark', ['tool' => $pattern, 'type' => 'pattern'])
        <div class="detail-grid">
            <div><span class="muted small">Pattern code</span><strong>{{ $pattern->code }}</strong></div>
            <div><span class="muted small">Name</span><strong>{{ $pattern->name }}</strong></div>
            <div><span class="muted small">Type</span><strong>{{ $pattern->pattern_type }}</strong></div>
            <div><span class="muted small">Material</span><strong>{{ $pattern->material ?: '—' }}</strong></div>
            <div><span class="muted small">Made for sand</span><strong>{{ $pattern->sand_system ?: 'not recorded' }}</strong>@if($pattern->compatible_sands)<div class="muted small">also works on {{ implode(', ', $pattern->compatible_sands) }}</div>@endif</div>
            <div>
                <span class="muted small">Dimensions (L × W × H)</span>
                <strong>
                    {{ $pattern->length_mm ?? '—' }} ×
                    {{ $pattern->width_mm ?? '—' }} ×
                    {{ $pattern->height_mm ?? '—' }} mm
                </strong>
            </div>
        </div>

        @if ($pattern->description)
            <div class="field-full" style="margin-top: 20px">
                <span class="muted small">Description</span>
                <p>{{ $pattern->description }}</p>
            </div>
        @endif

        @if ($pattern->notes)
            <div class="field-full" style="margin-top: 16px">
                <span class="muted small">General tooling notes</span>
                <p>{{ $pattern->notes }}</p>
            </div>
        @endif
    </div>
</div>

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
    <div class="panel-header"><h2>Engineering revisions</h2></div>

    @if ($pattern->revisions->isEmpty())
        <div class="empty-state">No revisions recorded.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Revision</th>
                        <th>Status</th>
                        <th>Parting</th>
                        <th>Cavities / mould</th>
                        <th>Layers / mould</th>
                        <th>Weight (kg)</th>
                        <th>Effective dates</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pattern->revisions as $revision)
                        <tr>
                            <td><strong>{{ $revision->revision }}</strong></td>
                            <td>{{ $revision->status }}</td>
                            <td>{{ $revision->parting_type ?: '—' }}</td>
                            <td>{{ $revision->cavities_per_mould ?? '—' }}</td>
                            <td>{{ $revision->layers_per_mould ?? '—' }}</td>
                            <td>
                                {{ $revision->pattern_weight_kg !== null
                                    ? number_format((float) $revision->pattern_weight_kg, 4)
                                    : '—' }}
                            </td>
                            <td>
                                {{ $revision->effective_from?->format('Y-m-d') ?? '—' }}
                                to
                                {{ $revision->effective_to?->format('Y-m-d') ?? '—' }}
                            </td>
                        </tr>
                        @if ($revision->change_description || $revision->engineering_notes)
                            <tr>
                                <td colspan="7">
                                    @if ($revision->change_description)
                                        <strong>Change:</strong> {{ $revision->change_description }}
                                    @endif
                                    @if ($revision->engineering_notes)
                                        <div class="muted small" style="margin-top: 5px">
                                            <strong>Engineering notes:</strong> {{ $revision->engineering_notes }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="tab-marker" data-tab-start="Cavity map"></div>
@include('patterns._cavity-map', ['pattern' => $pattern, 'productRevisions' => $productRevisions])

<div class="tab-marker" data-tab-start="New revision"></div>
<div class="panel" id="new-revision">
    <div class="panel-header">
        <h2>Add engineering revision</h2>
    </div>
    <div class="panel-body">
        <p class="muted small">
            This creates a new revision; it does not modify existing revisions.
            Record a meaningful change description for traceability.
        </p>

        @if ($errors->any())
            <div class="alert alert-error">
                <strong>Please correct the following:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('patterns.revisions.store', $pattern) }}">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label for="revision">Revision code *</label>
                    <input id="revision" name="revision" required maxlength="20"
                           value="{{ old('revision') }}" placeholder="e.g. B">
                </div>
                <div class="field">
                    <label for="status">Revision status *</label>
                    <select id="status" name="status" required>
                        @foreach (['DRAFT', 'APPROVED', 'OBSOLETE'] as $status)
                            <option value="{{ $status }}" @selected(old('status', 'DRAFT') === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="parting_type">Parting arrangement</label>
                    <input id="parting_type" name="parting_type" maxlength="30"
                           value="{{ old('parting_type') }}" placeholder="e.g. HORIZONTAL">
                </div>
                <div class="field">
                    <label for="cavities_per_mould">Cavities per mould</label>
                    <input id="cavities_per_mould" name="cavities_per_mould"
                           type="number" min="1" step="1" value="{{ old('cavities_per_mould') }}">
                </div>
                <div class="field">
                    <label for="layers_per_mould">Layers per mould</label>
                    <input id="layers_per_mould" name="layers_per_mould"
                           type="number" min="1" step="1" value="{{ old('layers_per_mould', 2) }}">
                </div>
                <div class="field">
                    <label for="pattern_weight_kg">Pattern / plate weight (kg)</label>
                    <input id="pattern_weight_kg" name="pattern_weight_kg"
                           type="number" min="0.0001" step="0.0001"
                           value="{{ old('pattern_weight_kg') }}">
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
                    <label for="change_description">Change description *</label>
                    <textarea id="change_description" name="change_description" rows="3"
                              required>{{ old('change_description') }}</textarea>
                </div>
                <div class="field field-full">
                    <label for="engineering_notes">Engineering notes</label>
                    <textarea id="engineering_notes" name="engineering_notes" rows="3">{{ old('engineering_notes') }}</textarea>
                </div>
            </div>
            <div class="actions" style="margin-top: 18px">
                <button class="btn btn-primary" type="submit">Save new revision</button>
            </div>
        </form>
    </div>
</div>
<div class="tab-marker" data-tab-start="Cooling"></div>
@include('patterns._cooling', ['pattern' => $pattern])
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'pattern', 'id' => $pattern->id])
@include('attachments._panel', ['type' => 'pattern', 'id' => $pattern->id])
@include('audit._history', ['type' => 'Pattern', 'id' => $pattern->id])
@endsection
