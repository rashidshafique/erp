@extends('layouts.app')
@section('title', 'Feasibility review')
@section('breadcrumb', 'Feasibility')
@section('content')
@php
    $fcls = ['PENDING' => 'status-on-hold', 'OK' => 'status-completed', 'CONDITIONAL' => 'status-in-progress', 'NOT_FEASIBLE' => 'status-cancelled'];
    $labels = \App\Domains\Enquiry\Models\EnquiryItem::FEASIBILITY;
    $open = $item->enquiry->isOpen();
    $signed = $item->reviews->keyBy('department');
    $outcome = $item->reviewOutcome();
@endphp
<div class="page-heading">
    <div><h1>{{ $item->line_no }}. {{ $item->description }}</h1><p><a href="{{ route('enquiries.show', $item->enquiry) }}">{{ $item->enquiry->code }}</a> · {{ $item->enquiry->customer?->name }} · {{ $item->material_text ?: 'Material not stated' }} · {{ $item->weight_kg ? \App\Support\Formats::number($item->weight_kg, 2).' kg' : 'weight unknown' }}</p></div>
    <div class="actions"><a class="btn" href="{{ route('feasibility.index') }}">All feasibility</a></div>
</div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
<div class="panel">
    <div class="panel-header"><h2>Overall</h2><span class="status {{ $fcls[$item->feasibility_status] }}">{{ $labels[$item->feasibility_status] }}</span></div>
    <div class="panel-body">
        <p>{{ $signed->count() }} of {{ count($departments) }} departments signed. @if ($outcome && $item->feasibility_status === 'PENDING')So far <strong>{{ strtolower($labels[$outcome]) }}</strong>.@endif</p>
        @if ($item->feasibility_notes)<p style="white-space:pre-line">{{ $item->feasibility_notes }}</p>@endif
        <p class="muted small">Prefer one step? Record a single decision on the <a href="{{ route('enquiries.show', $item->enquiry) }}">enquiry</a> instead; departments are optional.</p>
    </div>
</div>
<div class="panel"><div class="panel-header"><h2>Departments</h2></div>
<div class="table-wrap"><table>
    <thead><tr><th>Department</th><th>Question</th><th>Verdict</th><th>Notes</th><th>Signed</th></tr></thead>
    <tbody>
    @foreach ($departments as $key => [$name, $question])
        @php $r = $signed->get($key); @endphp
        <tr>
            <td><strong>{{ $name }}</strong></td><td class="small">{{ $question }}</td>
            @if ($open)
            <td colspan="2"><form method="POST" action="{{ route('feasibility.sign', [$item, $key]) }}" style="display:flex;gap:6px;flex-wrap:wrap">@csrf
                <select name="verdict">@foreach (['OK', 'CONDITIONAL', 'NOT_FEASIBLE'] as $v)<option value="{{ $v }}" @selected($r?->verdict === $v)>{{ $labels[$v] }}</option>@endforeach</select>
                <input name="notes" value="{{ $r?->notes }}" placeholder="Conditions or reason" style="min-width:220px;flex:1">
                <button class="btn" type="submit">{{ $r ? 'Re-sign' : 'Sign' }}</button></form></td>
            @else
            <td>@if ($r)<span class="status {{ $fcls[$r->verdict] }}">{{ $labels[$r->verdict] }}</span>@else<span class="muted">Pending</span>@endif</td><td class="small">{{ $r?->notes }}</td>
            @endif
            <td class="small">@if ($r){{ $r->signed_by_name }}<div class="muted">{{ $r->signed_at?->displayDate() }}</div>@else — @endif</td>
        </tr>
    @endforeach
    </tbody>
</table></div></div>
@endsection
