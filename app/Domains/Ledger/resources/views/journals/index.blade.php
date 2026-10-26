@extends('layouts.app')
@section('title', 'Journals')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Journals</h1><p>Every entry in the books. Invoices, bills, receipts and payments post their own; enter opening balances, depreciation or accruals by hand.</p></div>
<div class="actions"><a class="btn btn-primary" href="{{ route('ledger.journals.create') }}">+ Journal</a></div></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="{{ request('from') }}"></div>
    <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="{{ request('to') }}"></div>
    <div class="field"><label for="source">Posted by</label><select id="source" name="source"><option value="">Anything</option><option value="manual" @selected($source === 'manual')>Manual</option>@foreach (\App\Domains\Ledger\Models\Journal::SOURCES as $k => [$l])<option value="{{ $k }}" @selected($source === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="account">Account</label><select id="account" name="account"><option value="">Any account</option>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($accountId === $a->id)>{{ $a->label() }}</option>@endforeach</select></div>
    <button class="btn">Show</button>@if (request()->hasAny(['from', 'to', 'source', 'account']))<a class="btn" href="{{ route('ledger.journals.index') }}">Reset</a>@endif
</form></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Number</th><th>Date</th><th>Posted by</th><th>Memo</th><th class="num">Amount</th></tr></thead><tbody>
@forelse ($journals as $j)<tr><td><a href="{{ route('ledger.journals.show', $j) }}" class="mono">{{ $j->number }}</a>@if ($j->kind === 'REVERSAL') <span class="muted small">reversal</span>@endif</td><td>{{ $j->journal_date->displayShortDate() }}</td><td>{{ $j->sourceLabel() }}</td><td>{{ $j->memo }}</td><td class="num">{{ $m($j->total) }}</td></tr>
@empty<tr><td colspan="5" class="muted">No journal yet. Issuing an invoice or posting a bill writes the first one.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body">{{ $journals->links() }}</div></div>
@endsection
