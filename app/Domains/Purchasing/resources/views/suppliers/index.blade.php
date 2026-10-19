@extends('layouts.app')

@section('title', 'Suppliers')
@section('breadcrumb', 'Suppliers')

@section('content')
<div class="page-heading">
    <div><h1>Suppliers</h1><p>The approved supplier list. Material lots can name the supplier they came from; suspended suppliers cannot be received from.</p></div>
    <div class="actions"><a class="btn" href="{{ route('incoming.settings.edit') }}">Incoming rules</a><a class="btn btn-primary" href="{{ route('suppliers.create') }}">+ Add supplier</a></div>
</div>

<div class="panel"><div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px">
    @foreach (['PENDING' => ['Pending approval', 'var(--warning)', ['status' => 'PENDING']], 'review' => ['Review overdue', 'var(--danger)', ['state' => 'review']], 'CONDITIONAL' => ['Conditional', 'var(--warning)', ['status' => 'CONDITIONAL']], 'SUSPENDED' => ['Suspended / disqualified', 'var(--danger)', ['status' => 'SUSPENDED']]] as $key => [$label, $color, $link])
        <a href="{{ route('suppliers.index', $link) }}" style="text-decoration:none;color:inherit"><div class="muted small">{{ $label }}</div><strong style="font-size:22px;@if ($counts[$key]) color:{{ $color }} @endif">{{ $counts[$key] }}</strong></a>
    @endforeach
</div></div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" class="form-grid" style="align-items:end">
            <div class="field"><label for="q">Search code, name or what they supply</label><input id="q" name="q" value="{{ request('q') }}"></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option>@foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div><button class="btn btn-primary" type="submit">Filter</button> <a class="btn" href="{{ route('suppliers.index') }}">Clear</a></div>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Code</th><th>Supplier</th><th>Supplies</th><th>Status</th><th>Score</th><th>Next review</th></tr></thead>
        <tbody>
        @forelse ($suppliers as $supplier)
            <tr>
                <td class="mono"><a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->code }}</a></td>
                <td>{{ $supplier->name }}<div class="muted small">{{ collect([$supplier->contact_name, $supplier->phone])->filter()->implode(' · ') }}</div></td>
                <td>{{ $supplier->supplies ?: '—' }}</td>
                <td>{{ $statuses[$supplier->approval_status] }}</td>
                <td>{{ $supplier->latest_score ?? '—' }}</td>
                <td @if ($supplier->isReviewOverdue()) style="color:var(--danger)" @endif>{{ $supplier->next_review_on?->displayDate() ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No suppliers yet. Add the first one to start the list.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="panel-body">{{ $suppliers->links() }}</div>
</div>
@endsection
