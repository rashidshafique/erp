@extends('layouts.app')
@section('title', $policy->code)
@section('breadcrumb')<a href="{{ route('hr.policies.index') }}">HR policies</a> / {{ $policy->code }} @endsection
@section('content')
@php $draft = $policy->status === 'DRAFT'; @endphp
<div class="page-heading"><div><h1>{{ $policy->title }}</h1><p>{{ $policy->code }} · version {{ $policy->version }} · {{ config('hr.policy_categories')[$policy->category] }} · @include('hr._chip', ['s' => $policy->status])</p></div>
<div class="actions">
@if ($draft)<form method="POST" action="{{ route('hr.policies.publish', $policy) }}">@csrf<button class="btn btn-primary">Publish</button></form>@endif
@if ($policy->status === 'PUBLISHED')<form method="POST" action="{{ route('hr.policies.revise', $policy) }}" onsubmit="return confirm('Start a new version? Acknowledgements start again once it is published.')">@csrf<button class="btn">New version</button></form>@endif
@if ($policy->status !== 'RETIRED')<form method="POST" action="{{ route('hr.policies.retire', $policy) }}" onsubmit="return confirm('Retire this policy?')">@csrf<button class="btn">Retire</button></form>@endif
</div></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif

<div class="tab-marker" data-tab-start="Policy"></div>
<div class="panel"><div class="panel-body">
<form method="POST" action="{{ route('hr.policies.update', $policy) }}">@csrf @method('PUT')<div class="form-grid">
    @include('hr._in', ['name' => 'title', 'label' => 'Title', 'val' => $policy->title, 'required' => true])
    @include('hr._sel', ['name' => 'category', 'label' => 'Category', 'items' => config('hr.policy_categories'), 'sel' => $policy->category, 'blank' => false])
    @include('hr._in', ['name' => 'effective_on', 'label' => 'Effective from', 'type' => 'date', 'val' => $policy->effective_on?->toDateString()])
    <div class="field"><label><input type="checkbox" name="requires_ack" value="1" @checked($policy->requires_ack)> Everyone must acknowledge it</label></div>
    <div class="field field-full"><label for="f_body">Policy text</label><textarea id="f_body" name="body" rows="16" @unless ($draft) readonly @endunless>{{ old('body', $policy->body) }}</textarea></div>
</div><p><button class="btn btn-primary">Save</button></p></form></div></div>

<div class="tab-marker" data-tab-start="Acknowledgements"></div>
@if ($policy->status !== 'PUBLISHED')<div class="panel"><div class="empty-state">Acknowledgements open once the policy is published.</div></div>
@else
<form method="POST" action="{{ route('hr.policies.acknowledge', $policy) }}">@csrf
<div class="panel"><div class="panel-header"><h2>Version {{ $policy->version }}</h2><span class="muted small">{{ $acks->count() }} of {{ $employees->count() }} acknowledged</span></div>
<div class="table-wrap"><table><thead><tr><th style="width:36px"></th><th>Employee</th><th>Department</th><th>Acknowledged</th></tr></thead><tbody>
@foreach ($employees as $x)@php $a = $acks->get($x->id); @endphp
<tr><td>@unless ($a)<input type="checkbox" name="employee_ids[]" value="{{ $x->id }}">@endunless</td><td><a href="{{ route('hr.employees.show', $x) }}">{{ $x->code }}</a> {{ $x->full_name }}</td><td>{{ $x->department?->name ?? '—' }}</td>
<td>@if ($a)<span class="status status-completed">{{ $a->acknowledged_at->displayDate() }}</span>@else<span class="muted">—</span>@endif</td></tr>@endforeach
</tbody></table></div>
<div class="panel-body"><button class="btn btn-primary">Record acknowledgement for the ticked people</button></div></div></form>
@endif
@endsection
