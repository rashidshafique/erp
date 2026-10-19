@extends('layouts.app')

@section('title', $supplier->code.' · '.$supplier->name)
@section('breadcrumb')<a href="{{ route('suppliers.index') }}">Suppliers</a> / {{ $supplier->code }} @endsection

@section('content')
<div class="page-heading">
    <div><h1>{{ $supplier->name }}</h1><p><span class="mono">{{ $supplier->code }}</span> · {{ $statuses[$supplier->approval_status] }}</p></div>
    <div class="actions"><a class="btn" href="{{ route('suppliers.index') }}">All suppliers</a><a class="btn" href="{{ route('suppliers.edit', $supplier) }}">Edit</a></div>
</div>

@if ($supplier->isBlocked())<div class="alert alert-error">{{ $statuses[$supplier->approval_status] }}. Material lots cannot be received from this supplier.</div>
@elseif ($supplier->approval_status === 'PENDING')<div class="alert alert-warning">Not yet approved. Record an evaluation below to approve or reject it.</div>
@elseif ($supplier->isReviewOverdue())<div class="alert alert-error">Review was due {{ $supplier->next_review_on->displayDate() }}. Record a new evaluation.</div>@endif

<div class="panel"><div class="panel-header"><h2>Supplier</h2></div>
<div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px">
    @foreach ([
        'Supplies' => $supplier->supplies, 'Contact' => $supplier->contact_person, 'Phone' => $supplier->phone, 'Email' => $supplier->email,
        'Approved on' => $supplier->approved_on?->displayDate(), 'Next review' => $supplier->next_review_on?->displayDate(),
        'Latest score' => $supplier->latest_score, 'Review interval' => $supplier->review_interval_days.' days',
    ] as $label => $value)
        <div><div class="muted small">{{ $label }}</div><strong>{{ $value ?: '—' }}</strong></div>
    @endforeach
    @if ($supplier->address)<div style="grid-column:1/-1"><div class="muted small">Address</div>{{ $supplier->address }}</div>@endif
    @if ($supplier->notes)<div style="grid-column:1/-1"><div class="muted small">Notes</div>{{ $supplier->notes }}</div>@endif
</div></div>

<div class="panel"><div class="panel-header"><h2>Record an evaluation</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('suppliers.evaluate', $supplier) }}">@csrf
        <div class="form-grid">
            <div class="field"><label for="evaluated_on">Evaluated on *</label><input id="evaluated_on" type="date" name="evaluated_on" required max="{{ today()->toDateString() }}" value="{{ old('evaluated_on', today()->toDateString()) }}"></div>
            <div class="field"><label for="quality_score">Quality (0-100) *</label><input id="quality_score" type="number" name="quality_score" min="0" max="100" required value="{{ old('quality_score') }}"></div>
            <div class="field"><label for="delivery_score">Delivery (0-100) *</label><input id="delivery_score" type="number" name="delivery_score" min="0" max="100" required value="{{ old('delivery_score') }}"></div>
            <div class="field"><label for="service_score">Service (0-100) *</label><input id="service_score" type="number" name="service_score" min="0" max="100" required value="{{ old('service_score') }}"></div>
            <div class="field"><label for="decision">Decision *</label><select id="decision" name="decision">@foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected(old('decision', 'APPROVED') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field field-full"><label for="notes">Notes</label><input id="notes" name="notes" maxlength="2000" value="{{ old('notes') }}"></div>
        </div>
        <button class="btn btn-primary" type="submit">Save evaluation</button>
        <span class="muted small">The decision becomes the supplier's status and sets the next review date.</span>
    </form>
</div></div>

<div class="panel"><div class="panel-header"><h2>Evaluation history</h2></div>
<div class="table-wrap"><table>
    <thead><tr><th>Date</th><th>Quality</th><th>Delivery</th><th>Service</th><th>Overall</th><th>Decision</th><th>By</th><th>Notes</th></tr></thead>
    <tbody>
    @forelse ($supplier->evaluations as $evaluation)
        <tr><td>{{ $evaluation->evaluated_on->displayDate() }}</td><td>{{ $evaluation->quality_score }}</td><td>{{ $evaluation->delivery_score }}</td><td>{{ $evaluation->service_score }}</td><td><strong>{{ $evaluation->overall_score }}</strong></td><td>{{ $statuses[$evaluation->decision] ?? $evaluation->decision }}</td><td>{{ $evaluation->recorded_by ?: '—' }}</td><td>{{ $evaluation->notes ?: '—' }}</td></tr>
    @empty
        <tr><td colspan="8" class="muted">Not evaluated yet.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
@endsection
