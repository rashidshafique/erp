@extends('layouts.app')

@section('title', $document->document_number)
@section('breadcrumb')<a href="{{ route('documents.index') }}">Controlled documents</a> / {{ $document->document_number }} @endsection

@section('content')
@php
    $current = $document->currentRevision;
    $editable = $canManage && $document->status !== 'OBSOLETE';
@endphp
<div class="page-heading">
    <div>
        <h1>{{ $document->title }}</h1>
        <p><span class="mono">{{ $document->document_number }}</span> · {{ \App\Domains\Documents\Models\ControlledDocument::TYPES[$document->document_type] ?? $document->document_type }} · @include('documents::_status', ['status' => $document->status, 'labels' => ['DRAFT' => 'Draft, not yet in force', 'ACTIVE' => 'In force', 'OBSOLETE' => 'Obsolete']])@if ($document->owner_name || $document->owning_department) · {{ $document->owner_name }}{{ $document->owner_name && $document->owning_department ? ', ' : '' }}{{ $document->owning_department }}@endif</p>
    </div>
    <div class="actions"><a class="btn" href="{{ route('documents.index') }}">All documents</a></div>
</div>

@if ($document->isDueForReview())
<div class="alert alert-warning" style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">
    <span>This document was due for review on {{ $document->next_review_on->displayDate() }}.</span>
    @if ($canManage)<form method="POST" action="{{ route('documents.reviewed', $document) }}">@csrf<button class="btn" type="submit">Reviewed, no change needed</button></form>@endif
</div>
@endif
@if ($document->status === 'OBSOLETE')<div class="alert alert-warning">Obsolete since {{ $document->obsolete_at?->displayDate() }}. Do not use.</div>@endif

<div class="panel"><div class="panel-header"><h2>In force</h2></div><div class="panel-body">
    @if ($current && $current->status === 'EFFECTIVE')
        <p><strong>Revision <span class="mono">{{ $current->revision_code }}</span></strong> since {{ $current->effective_at?->displayDate() }} · approved by {{ $current->approved_by ?: '—' }}</p>
        @if ($current->revision_summary)<p>{{ $current->revision_summary }}</p>@endif
        <p><a class="btn btn-primary" href="{{ route('documents.revisions.file', $current) }}" target="_blank" rel="noopener">Open {{ $current->original_filename }}</a> <span class="muted small">{{ number_format($current->file_size_bytes / 1024, 0) }} KB · checksum {{ substr($current->file_sha256, 0, 12) }}</span></p>
        @if ($document->next_review_on || $document->last_reviewed_on)<p class="muted small">@if ($document->last_reviewed_on)Last reviewed {{ $document->last_reviewed_on->displayDate() }}{{ $document->last_reviewed_by ? ' by '.$document->last_reviewed_by : '' }}. @endif @if ($document->next_review_on)Next review {{ $document->next_review_on->displayDate() }}.@endif</p>@endif
    @else
        <p class="muted">No revision is in force yet.</p>
    @endif
</div></div>

@if ($canSeeAll && $open)
<div class="panel"><div class="panel-header"><h2>Revision {{ $open->revision_code }} @include('documents::_status', ['status' => $open->status])</h2></div><div class="panel-body">
    <p class="muted small">Prepared by {{ $open->prepared_by ?: '—' }}{{ $open->submitted_at ? ' · submitted '.$open->submitted_at->displayDate() : '' }}</p>
    @if ($open->change_reason)<p><strong>Why:</strong> {{ $open->change_reason }}</p>@endif
    @if ($open->revision_summary)<p>{{ $open->revision_summary }}</p>@endif
    <p><a href="{{ route('documents.revisions.file', $open) }}" target="_blank" rel="noopener">{{ $open->original_filename }}</a> <span class="muted small">{{ number_format($open->file_size_bytes / 1024, 0) }} KB</span></p>

    @if ($open->status === 'DRAFT' && $canManage)
        <form method="POST" action="{{ route('documents.revisions.update', $open) }}" enctype="multipart/form-data" class="form-grid">@csrf @method('PUT')
            <div class="field field-full"><label for="change_reason">Why this revision *</label><textarea id="change_reason" name="change_reason" rows="2" required maxlength="2000">{{ old('change_reason', $open->change_reason) }}</textarea></div>
            <div class="field field-full"><label for="revision_summary">Summary</label><textarea id="revision_summary" name="revision_summary" rows="2" maxlength="2000">{{ old('revision_summary', $open->revision_summary) }}</textarea></div>
            <div class="field"><label for="draft_file">Replace the file</label><input id="draft_file" type="file" name="file"></div>
            <div><button class="btn" type="submit">Save draft</button></div>
        </form>
        <hr>
        <form method="POST" action="{{ route('documents.revisions.submit', $open) }}">@csrf
            <h3 style="margin:0 0 8px">Send for approval</h3>
            <p class="muted small">Approvers decide in this order. Everyone has to approve before the revision takes effect.</p>
            <div class="form-grid">
                @foreach ([1 => 'First approver', 2 => 'Second approver (optional)', 3 => 'Third approver (optional)'] as $n => $label)
                    @if ($loginRequired)
                        <div class="field"><label for="approver_{{ $n }}">{{ $label }}</label><select id="approver_{{ $n }}" name="approvers[]"><option value="">—</option>@foreach ($approvers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
                    @else
                        <div class="field"><label for="approver_{{ $n }}">{{ $label }}</label><input id="approver_{{ $n }}" name="approver_names[]" maxlength="150"></div>
                    @endif
                @endforeach
            </div>
            @if ($loginRequired && $approvers->isEmpty())<p class="muted small">Nobody else has the "approve controlled documents" permission yet. Give it to a role on the Roles screen.</p>@endif
            <button class="btn btn-primary" type="submit">Submit for approval</button>
        </form>
    @endif

    @if ($open->approvals->isNotEmpty())
        <h3 style="margin:16px 0 8px">Approval</h3>
        <div class="table-wrap"><table>
            <thead><tr><th>Step</th><th>Approver</th><th>Decision</th><th>Decided</th><th>Comments</th></tr></thead>
            <tbody>@foreach ($open->approvals as $step)
                <tr><td>{{ $step->approval_step }}</td><td>{{ $step->approver_name }}</td><td>@include('documents::_status', ['status' => $step->decision, 'labels' => \App\Domains\Documents\Models\DocumentApprovalRecord::DECISIONS])</td><td>{{ $step->decided_at?->displayDateTime() ?? '—' }}</td><td>{{ $step->comments ?: '—' }}</td></tr>
            @endforeach</tbody>
        </table></div>
        @php($next = $open->status === 'IN_REVIEW' ? $open->approvals->firstWhere('decision', 'PENDING') : null)
        @if ($next && (! $loginRequired || $isAdmin || $next->approver_identifier === $myUserId))
            <form method="POST" action="{{ route('documents.revisions.decide', $next) }}" class="form-grid" style="margin-top:12px">@csrf
                <div class="field field-full"><label for="comments">Comments (needed to reject)</label><textarea id="comments" name="comments" rows="2" maxlength="2000"></textarea></div>
                <div><button class="btn btn-primary" type="submit" name="decision" value="APPROVED">Approve</button> <button class="btn" type="submit" name="decision" value="REJECTED">Reject</button></div>
            </form>
        @endif
    @endif
</div></div>
@endif

@if ($editable && ! $open)
<div class="panel"><div class="panel-header"><h2>New revision</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('documents.revisions.store', $document) }}" enctype="multipart/form-data" class="form-grid">@csrf
        <div class="field"><label for="revision_code">Revision *</label><input id="revision_code" name="revision_code" required maxlength="50" value="{{ old('revision_code', $nextCode) }}"></div>
        <div class="field"><label for="file">File *</label><input id="file" type="file" name="file" required></div>
        <div class="field field-full"><label for="new_change_reason">Why this revision *</label><textarea id="new_change_reason" name="change_reason" rows="2" required maxlength="2000">{{ old('change_reason') }}</textarea></div>
        <div class="field field-full"><label for="new_summary">Summary</label><textarea id="new_summary" name="revision_summary" rows="2" maxlength="2000">{{ old('revision_summary') }}</textarea></div>
        <div><button class="btn btn-primary" type="submit">Start the revision as a draft</button></div>
    </form>
</div></div>
@endif

@if ($canSeeAll)
<div class="panel"><div class="panel-header"><h2>History</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Revision</th><th>Status</th><th>Why</th><th>Prepared</th><th>Approved</th><th>In force</th><th>Replaced</th><th>File</th></tr></thead>
        <tbody>@forelse ($document->revisions as $rev)
            <tr>
                <td class="mono">{{ $rev->revision_code }}</td>
                <td>@include('documents::_status', ['status' => $rev->status])</td>
                <td>{{ $rev->change_reason ?: '—' }}</td>
                <td>{{ $rev->prepared_by ?: '—' }}</td>
                <td>{{ $rev->approved_at ? $rev->approved_at->displayDate().' · '.$rev->approved_by : '—' }}</td>
                <td>{{ $rev->effective_at?->displayDate() ?? '—' }}</td>
                <td>{{ $rev->superseded_at?->displayDate() ?? '—' }}</td>
                <td>@if ($rev->hasFile())<a href="{{ route('documents.revisions.file', $rev) }}" target="_blank" rel="noopener">{{ $rev->original_filename }}</a>@else — @endif</td>
            </tr>
        @empty<tr><td colspan="8" class="muted">No revisions.</td></tr>@endforelse</tbody>
    </table></div>
</div>
@endif

@if ($editable)
<div class="panel"><div class="panel-header"><h2>Details</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('documents.update', $document) }}" class="form-grid">@csrf @method('PUT')
        <div class="field field-full"><label for="title">Title *</label><input id="title" name="title" required maxlength="250" value="{{ old('title', $document->title) }}"></div>
        <div class="field"><label for="document_type">Type</label><select id="document_type" name="document_type">@foreach (\App\Domains\Documents\Models\ControlledDocument::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('document_type', $document->document_type) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="owner_name">Owner</label><input id="owner_name" name="owner_name" maxlength="150" value="{{ old('owner_name', $document->owner_name) }}"></div>
        <div class="field"><label for="owning_department">Department</label><input id="owning_department" name="owning_department" maxlength="100" value="{{ old('owning_department', $document->owning_department) }}"></div>
        <div class="field"><label for="review_interval_months">Review every (months)</label><input id="review_interval_months" type="number" name="review_interval_months" min="1" max="120" value="{{ old('review_interval_months', $document->review_interval_months) }}"></div>
        <div><button class="btn" type="submit">Save</button></div>
    </form>
</div></div>
@if ($document->status === 'ACTIVE' && ! $open)
<div class="panel"><div class="panel-header"><h2>Make obsolete</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('documents.obsolete', $document) }}" class="form-grid">@csrf
        <div class="field field-full"><label for="reason">Why it is no longer used *</label><textarea id="reason" name="reason" rows="2" required maxlength="500"></textarea></div>
        <div><button class="btn" type="submit">Make obsolete</button></div>
    </form>
</div></div>
@endif
@endif
@endsection
