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
        <button type="button" class="btn btn-primary" data-quick="qd-revision">+ New revision</button>
    </div>
</div>

@php $current = $pattern->revisions->firstWhere('is_current', true); @endphp
@include('partials.quick-dialog', ['id' => 'qd-revision', 'title' => 'New revision of '.$pattern->code, 'action' => route('patterns.revisions.store', $pattern), 'submit' => 'Create revision', 'fields' => 'patterns._quick-revision', 'multipart' => true, 'current' => $current])

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
                        <th>Cavities / mould</th>
                        <th>Parting</th>
                        <th>Effective</th>
                        <th>Photo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pattern->revisions as $revision)
                        <tr>
                            <td><strong class="mono">{{ $revision->code ?? $revision->revision }}</strong></td>
                            <td>
                                @if ($revision->is_current)<span class="status status-completed">Current</span>
                                @elseif ($revision->isSuperseded())<span class="status status-on-hold" title="Superseded {{ $revision->superseded_at->format('d M Y') }}">Superseded</span>
                                @else<span class="status">Not current</span>@endif
                                @if ($revision->legacy_baseline)<span class="status" title="Created from the imported data, not from a recorded change">Baseline</span>@endif
                            </td>
                            <td>{{ $revision->status }}</td>
                            <td>{{ $revision->drawing_revision ?: '—' }}</td>
                            <td class="mono">@if ($revision->modification_cost !== null){{ number_format((float) $revision->modification_cost) }}@elseif ($revision->legacy_baseline)<span class="muted">not recorded</span>@else — @endif</td>
                            <td class="mono small">@if ($revision->casting_weight_kg || $revision->pour_weight_kg){{ $revision->casting_weight_kg !== null ? rtrim(rtrim(number_format((float) $revision->casting_weight_kg, 3), '0'), '.') : '—' }} / {{ $revision->pour_weight_kg !== null ? rtrim(rtrim(number_format((float) $revision->pour_weight_kg, 3), '0'), '.') : '—' }}@if ($revision->yieldPercent() !== null)<div class="muted">yield {{ $revision->yieldPercent() }} %@if ($revision->pieces_per_mould) · {{ $revision->pieces_per_mould }} pcs/mould @endif</div>@endif @elseif ($revision->legacy_baseline)<span class="muted">not recorded</span>@else — @endif</td>
                            <td>{{ $revision->cavities_per_mould ?? '—' }}</td>
                            <td>{{ $revision->parting_type ?: '—' }}</td>
                            <td class="small">{{ $revision->effective_from?->format('d M Y') ?? '—' }} – {{ $revision->effective_to?->format('d M Y') ?? '' }}</td>
                            <td>@foreach ($revisionPhotos->get($revision->id, collect()) as $photo)<a href="{{ route('attachments.download', $photo) }}" target="_blank">View</a> @endforeach</td>
                        </tr>
                        @if ($revision->change_description || $revision->shrinkage_note || $revision->engineering_notes || $revision->cost_note)
                            <tr>
                                <td colspan="10" class="small">
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

<div class="tab-marker" data-tab-start="Cavity map"></div>
@include('patterns._cavity-map', ['pattern' => $pattern, 'productRevisions' => $productRevisions])

<div class="tab-marker" data-tab-start="Cooling"></div>
@include('patterns._cooling', ['pattern' => $pattern])
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'pattern', 'id' => $pattern->id])
@include('attachments._panel', ['type' => 'pattern', 'id' => $pattern->id])
@include('audit._history', ['type' => 'Pattern', 'id' => $pattern->id])
@endsection
