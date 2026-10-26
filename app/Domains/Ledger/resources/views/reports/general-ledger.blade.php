@extends('layouts.app')
@section('title', 'General ledger')
@push('styles')<style>@media print{.sidebar,.page-heading .actions,.no-print{display:none!important}}</style>@endpush
@section('content')
@php
    $m = fn ($v) => $v > 0 ? \App\Support\Formats::number($v, 2) : '';
    $bal = fn ($v) => \App\Support\Formats::number(abs($v), 2).($v > 0 ? ' Dr' : ($v < 0 ? ' Cr' : ''));
    $d = fn (string $date) => \Illuminate\Support\Carbon::parse($date)->displayDate();
@endphp
<div class="page-heading"><div><h1>General ledger</h1><p>{{ $d($from) }} to {{ $d($to) }}: every account that carries a balance into the period or moved in it, with its postings and running balance.</p></div>
<div class="actions"><a class="btn" href="{{ route('ledger.trial-balance', ['as_at' => $to]) }}">Trial balance</a><button class="btn btn-primary" onclick="window.print()">Print</button></div></div>
<div class="panel no-print"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="{{ $from }}"></div>
    <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="{{ $to }}"></div>
    <div class="field"><label for="type">Accounts</label><select id="type" name="type"><option value="">All</option>@foreach (\App\Domains\Ledger\Models\LedgerAccount::TYPES as $k => $l)<option value="{{ $k }}" @selected($type === $k)>{{ $l }}</option>@endforeach</select></div>
    <button class="btn">Show</button>
</form></div></div>
@forelse ($accounts as $row)
@php $running = $row['opening']; @endphp
<div class="panel"><div class="panel-header"><h2><a href="{{ route('ledger.accounts.show', [$row['account'], 'from' => $from, 'to' => $to]) }}">{{ $row['account']->label() }}</a></h2></div>
<div class="table-wrap"><table><thead><tr><th style="width:110px">Date</th><th style="width:120px">Journal</th><th>Memo</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead><tbody>
<tr><td>{{ \Illuminate\Support\Carbon::parse($from)->displayShortDate() }}</td><td colspan="4" class="muted">Opening balance</td><td class="num">{{ $bal($row['opening']) }}</td></tr>
@foreach ($row['lines'] as $l)
@php $running = round($running + $l->debit - $l->credit, 2); @endphp
<tr><td>{{ $l->journal->journal_date->displayShortDate() }}</td><td><a href="{{ route('ledger.journals.show', $l->journal) }}" class="mono">{{ $l->journal->number }}</a></td>
<td class="small">{{ $l->journal->memo }} @if ($l->description)<span class="muted">· {{ $l->description }}</span>@endif</td>
<td class="num">{{ $m($l->debit) }}</td><td class="num">{{ $m($l->credit) }}</td><td class="num">{{ $bal($running) }}</td></tr>
@endforeach
</tbody><tfoot><tr><th colspan="3" class="num">Closing balance</th><th class="num">{{ \App\Support\Formats::number($row['debit'], 2) }}</th><th class="num">{{ \App\Support\Formats::number($row['credit'], 2) }}</th><th class="num">{{ $bal($row['closing']) }}</th></tr></tfoot></table></div></div>
@empty
<div class="panel"><div class="panel-body muted">Nothing is posted in this period and no account carries a balance into it.</div></div>
@endforelse
@endsection
