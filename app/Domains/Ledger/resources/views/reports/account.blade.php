@extends('layouts.app')
@section('title', $account->label())
@section('content')
@php $m = fn ($v) => $v > 0 ? \App\Support\Formats::number($v, 2) : ''; $bal = fn ($v) => \App\Support\Formats::number(abs($v), 2).($v > 0 ? ' Dr' : ($v < 0 ? ' Cr' : '')); $running = $opening; @endphp
<div class="page-heading"><div><h1>{{ $account->label() }}</h1><p>{{ \App\Domains\Ledger\Models\LedgerAccount::TYPES[$account->type] }}@if ($account->parent) · under {{ $account->parent->label() }}@endif @if ($account->description) · {{ $account->description }}@endif</p></div>
<div class="actions"><a class="btn" href="{{ route('ledger.accounts.index') }}">Chart of accounts</a></div></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="{{ $from }}"></div>
    <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="{{ $to }}"></div>
    <button class="btn">Show</button>
</form></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Journal</th><th>Memo</th>@if ($account->isGroup())<th>Account</th>@endif<th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead><tbody>
<tr><td>{{ \Illuminate\Support\Carbon::parse($from)->displayShortDate() }}</td><td colspan="{{ $account->isGroup() ? 5 : 4 }}" class="muted">Opening balance</td><td class="num">{{ $bal($opening) }}</td></tr>
@foreach ($lines as $l)
@php $running = round($running + $l->debit - $l->credit, 2); @endphp
<tr><td>{{ $l->journal->journal_date->displayShortDate() }}</td><td><a href="{{ route('ledger.journals.show', $l->journal) }}" class="mono">{{ $l->journal->number }}</a></td>
<td class="small">@if ($url = $l->journal->sourceUrl())<a href="{{ $url }}">{{ $l->journal->memo }}</a>@else{{ $l->journal->memo }}@endif @if ($l->description)<span class="muted">· {{ $l->description }}</span>@endif</td>
@if ($account->isGroup())<td class="small">{{ $l->account->label() }}</td>@endif
<td class="num">{{ $m($l->debit) }}</td><td class="num">{{ $m($l->credit) }}</td><td class="num">{{ $bal($running) }}</td></tr>
@endforeach
</tbody><tfoot><tr><th colspan="{{ $account->isGroup() ? 4 : 3 }}" class="num">Closing balance</th><th class="num">{{ \App\Support\Formats::number($lines->sum('debit'), 2) }}</th><th class="num">{{ \App\Support\Formats::number($lines->sum('credit'), 2) }}</th><th class="num">{{ $bal($running) }}</th></tr></tfoot></table></div></div>
@endsection
