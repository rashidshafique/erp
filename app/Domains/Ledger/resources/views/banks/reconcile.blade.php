@extends('layouts.app')
@section('title', 'Statement to '.$rec->statement_date->displayDate())
@section('content')
@php
    $m = fn ($v) => $v > 0 ? \App\Support\Formats::number($v, 2) : '';
    $n = fn ($v) => \App\Support\Formats::number($v, 2);
    $amounts = $lines->mapWithKeys(fn ($l) => [$l->id => [$l->debit, $l->credit]]);
    $done = $rec->isDone();
@endphp
<div class="page-heading"><div><h1>{{ $rec->bankAccount->label() }}: statement to {{ $rec->statement_date->displayDate() }}</h1>
<p>@if ($done)Reconciled {{ $rec->completed_at?->displayDate() }}@if ($rec->user) by {{ $rec->user->name }}@endif. These are the lines that cleared on it.@else Tick each line that appears on the statement. When the difference is nil, finish. Lines on the statement but not in the books (bank charges, profit paid) can be posted below.@endif</p></div>
<div class="actions"><a class="btn" href="{{ route('ledger.banks.show', $rec->bankAccount) }}">{{ $rec->bankAccount->bank_name }}</a>
@if ($done)<form method="POST" action="{{ route('ledger.reconciliations.reopen', $rec) }}">@csrf<button class="btn" onclick="return confirm('Open this statement again to change its ticks?')">Open again</button></form>
@else<form method="POST" action="{{ route('ledger.reconciliations.destroy', $rec) }}">@csrf @method('DELETE')<button class="btn" onclick="return confirm('Discard this statement and its ticks? Nothing in the books changes.')">Discard</button></form>@endif</div></div>
@error('statement')<div class="alert alert-error">{{ $message }}</div>@enderror
<div x-data="reconcile(@js($ticked), @js($amounts), {{ $rec->opening_balance }}, {{ $rec->statement_balance }})">
<form method="POST" action="{{ route('ledger.reconciliations.update', $rec) }}">@csrf @method('PUT')
<div class="panel"><div class="panel-body" style="display:flex;gap:28px;flex-wrap:wrap">
    <div><div class="muted small">Opening (last statement)</div><strong>{{ $n($rec->opening_balance) }}</strong></div>
    <div><div class="muted small">Money in ticked</div><strong x-text="money(moneyIn)">{{ $n($summary['in']) }}</strong></div>
    <div><div class="muted small">Money out ticked</div><strong x-text="money(moneyOut)">{{ $n($summary['out']) }}</strong></div>
    <div><div class="muted small">Cleared balance</div><strong x-text="money(cleared)">{{ $n($summary['cleared']) }}</strong></div>
    <div><div class="muted small">Statement balance</div><strong>{{ $n($rec->statement_balance) }}</strong></div>
    <div><div class="muted small">Difference</div><strong x-text="money(difference)" :style="Math.abs(difference) > 0.004 ? 'color:var(--danger)' : 'color:var(--success)'">{{ $n($summary['difference']) }}</strong></div>
    <div><div class="muted small">Books as at the statement date</div><strong>{{ $n($summary['book']) }}</strong></div>
</div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr>@unless ($done)<th style="width:40px"><input type="checkbox" aria-label="Tick every line" @change="ticked = $event.target.checked ? Object.keys(amounts) : []"></th>@endunless<th>Date</th><th>Journal</th><th>Memo</th><th class="num">Money in</th><th class="num">Money out</th></tr></thead><tbody>
@forelse ($lines as $l)
<tr>@unless ($done)<td><input type="checkbox" name="lines[]" value="{{ $l->id }}" x-model="ticked" aria-label="Cleared"></td>@endunless
<td>{{ $l->journal->journal_date->displayShortDate() }}</td><td><a class="mono" href="{{ route('ledger.journals.show', $l->journal) }}">{{ $l->journal->number }}</a></td>
<td class="small">@if ($url = $l->journal->sourceUrl())<a href="{{ $url }}">{{ $l->journal->memo }}</a>@else{{ $l->journal->memo }}@endif @if ($l->description)<span class="muted">· {{ $l->description }}</span>@endif</td>
<td class="num">{{ $m($l->debit) }}</td><td class="num">{{ $m($l->credit) }}</td></tr>
@empty<tr><td colspan="{{ $done ? 5 : 6 }}" class="muted">Nothing in the books for this bank up to the statement date is waiting to be cleared.</td></tr>@endforelse
</tbody></table></div>
@unless ($done)<div class="panel-body"><button class="btn">Save ticks</button> <button class="btn btn-primary" name="finish" value="1" :disabled="Math.abs(difference) > 0.004">Finish</button></div>@endunless
</div></form>
@unless ($done)
<div class="panel"><div class="panel-header"><h2>On the statement but not in the books</h2></div>
@error('statement_line')<div class="panel-body"><div class="alert alert-error">{{ $message }}</div></div>@enderror
<form method="POST" action="{{ route('ledger.reconciliations.line', $rec) }}">@csrf
<template x-for="id in ticked" :key="id"><input type="hidden" name="lines[]" :value="id"></template>
<div class="panel-body form-grid">
    <label>Date<input type="date" name="date" value="{{ old('date', $rec->statement_date->toDateString()) }}" max="{{ $rec->statement_date->toDateString() }}" required></label>
    <label>Money<select name="direction"><option value="out" @selected(old('direction') !== 'in')>Out: a bank charge, tax deducted</option><option value="in" @selected(old('direction') === 'in')>In: profit or interest paid</option></select></label>
    <label>Amount<input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required></label>
    <label>Against account<select name="account_id" required><option value="">—</option>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('account_id', '') === $a->id)>{{ $a->label() }}</option>@endforeach</select></label>
    <label>Memo<input name="memo" value="{{ old('memo', 'Bank charges') }}" maxlength="300" required></label>
</div><div class="panel-body"><button class="btn">Post and tick</button> <span class="muted small">Posts a journal between this bank and the account chosen, and ticks it. Your other ticks are kept.</span></div></form></div>
@endunless
</div>
<script>
function reconcile(ticked, amounts, opening, statement) {
    return {
        ticked, amounts,
        sum(i) { return this.ticked.reduce((n, id) => n + (this.amounts[id] ? Number(this.amounts[id][i]) : 0), 0); },
        get moneyIn() { return this.sum(0); },
        get moneyOut() { return this.sum(1); },
        get cleared() { return Math.round((opening + this.moneyIn - this.moneyOut) * 100) / 100; },
        get difference() { return Math.round((statement - this.cleared) * 100) / 100; },
        money(v) { return (Math.round(v * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
</script>
@endsection
