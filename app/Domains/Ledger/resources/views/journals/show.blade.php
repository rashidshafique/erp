@extends('layouts.app')
@section('title', $journal->number)
@section('content')
@php $m = fn ($v) => $v > 0 ? \App\Support\Formats::number($v, 2) : ''; @endphp
<div class="page-heading"><div><h1>Journal {{ $journal->number }}</h1><p>{{ $journal->journal_date->displayDate() }} · {{ $journal->sourceLabel() }}@if ($url = $journal->sourceUrl()) · <a href="{{ $url }}">open</a>@endif
@if ($journal->user) · by {{ $journal->user->name }}@endif</p></div>
<div class="actions"><a class="btn" href="{{ route('ledger.journals.index') }}">All journals</a></div></div>
@error('journal')<div class="alert alert-error">{{ $message }}</div>@enderror
@if ($journal->reverses)<div class="alert alert-warning">Reverses <a href="{{ route('ledger.journals.show', $journal->reverses) }}">{{ $journal->reverses->number }}</a>.</div>@endif
@if ($journal->reversal)<div class="alert alert-warning">Reversed by <a href="{{ route('ledger.journals.show', $journal->reversal) }}">{{ $journal->reversal->number }}</a> on {{ $journal->reversal->journal_date->displayDate() }}.</div>@endif
<div class="panel">@if ($journal->memo)<div class="panel-body">{{ $journal->memo }}</div>@endif
<div class="table-wrap"><table><thead><tr><th>Account</th><th>Detail</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>
@foreach ($journal->lines as $l)<tr><td><a href="{{ route('ledger.accounts.show', $l->account) }}">{{ $l->account->label() }}</a></td><td class="small">{{ $l->description }}@if ($l->party_type) <span class="muted">{{ $l->party_type }}</span>@endif</td><td class="num">{{ $m($l->debit) }}</td><td class="num">{{ $m($l->credit) }}</td></tr>@endforeach
</tbody><tfoot><tr><th colspan="2" class="num">Total</th><th class="num">{{ \App\Support\Formats::number($journal->lines->sum('debit'), 2) }}</th><th class="num">{{ \App\Support\Formats::number($journal->lines->sum('credit'), 2) }}</th></tr></tfoot></table></div></div>
@if ($journal->source_type === null && $journal->kind === 'POST' && ! $journal->reversal)
<div class="panel"><div class="panel-body"><form method="POST" action="{{ route('ledger.journals.reverse', $journal) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">@csrf
<label style="margin:0">Reverse on <input type="date" name="reverse_on" value="{{ today()->toDateString() }}" required></label><button class="btn" onclick="return confirm('Post the reversal of this journal?')">Reverse</button>
<span class="muted small">A journal is never edited: reverse it and post a corrected one.</span></form></div></div>
@endif
@endsection
