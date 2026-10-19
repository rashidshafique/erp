@extends('layouts.app')

@section('title', 'Controlled documents')
@section('breadcrumb', 'Controlled documents')

@section('content')
<div class="page-heading">
    <div><h1>Controlled documents</h1><p>Procedures, work instructions, forms and specifications. Each has one revision in force; a change is a new revision that is approved before it replaces the old one.</p></div>
    @if ($canManage)<div class="actions"><a class="btn btn-primary" href="{{ route('documents.create') }}">+ New document</a></div>@endif
</div>

<div class="panel"><div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px">
    @foreach (['active' => ['In force', null], 'review' => ['Waiting for approval', 'var(--warning)'], 'due' => ['Due for review', 'var(--danger)'], 'obsolete' => ['Obsolete', null]] as $key => [$label, $color])
        @continue(! $canSeeAll && in_array($key, ['review', 'obsolete'], true))
        <a href="{{ route('documents.index', ['state' => $key]) }}" style="text-decoration:none;color:inherit"><div class="muted small">{{ $label }}</div><strong style="font-size:22px;@if ($color && $counts[$key]) color:{{ $color }} @endif">{{ $counts[$key] }}</strong></a>
    @endforeach
</div></div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" class="form-grid" style="align-items:end">
            <div class="field"><label for="q">Search number, title or owner</label><input id="q" name="q" value="{{ request('q') }}"></div>
            <div class="field"><label for="state">Show</label><select id="state" name="state">@foreach (($canSeeAll ? ['open' => 'Everything not obsolete', 'active' => 'In force', 'review' => 'Waiting for approval', 'due' => 'Due for review', 'obsolete' => 'Obsolete'] : ['active' => 'In force', 'due' => 'Due for review']) as $k => $l)<option value="{{ $k }}" @selected($state === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="type">Type</label><select id="type" name="type"><option value="">All</option>@foreach (\App\Domains\Documents\Models\ControlledDocument::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div><button class="btn btn-primary" type="submit">Filter</button> <a class="btn" href="{{ route('documents.index') }}">Clear</a></div>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Number</th><th>Title</th><th>Type</th><th>Revision in force</th><th>Owner</th><th>Next review</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($documents as $doc)
            @php($open = $canSeeAll ? $doc->openRevision() : null)
            <tr>
                <td class="mono"><a href="{{ route('documents.show', $doc) }}">{{ $doc->document_number }}</a></td>
                <td>{{ $doc->title }}</td>
                <td>{{ \App\Domains\Documents\Models\ControlledDocument::TYPES[$doc->document_type] ?? $doc->document_type }}</td>
                <td>@if ($doc->currentRevision)<span class="mono">{{ $doc->currentRevision->revision_code }}</span> <span class="muted small">from {{ $doc->currentRevision->effective_at?->displayDate() }}</span>@else — @endif
                    @if ($open)<div class="muted small">Revision {{ $open->revision_code }}: {{ \App\Domains\Documents\Models\ControlledDocumentRevision::STATUSES[$open->status] }}</div>@endif</td>
                <td>{{ $doc->owner_name ?: ($doc->owning_department ?: '—') }}</td>
                <td>@if ($doc->next_review_on){{ $doc->next_review_on->displayDate() }}@if ($doc->isDueForReview()) <span class="status status-cancelled">Due</span>@endif @else — @endif</td>
                <td>@include('documents::_status', ['status' => $doc->status, 'labels' => ['DRAFT' => 'Draft', 'ACTIVE' => 'In force', 'OBSOLETE' => 'Obsolete']])</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Nothing here.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="panel-body">{{ $documents->links() }}</div>
</div>
@endsection
