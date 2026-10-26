@extends('layouts.app')
@section('title', 'New journal')
@section('content')
@php $start = old('lines', [['account_id' => '', 'debit' => '', 'credit' => '', 'description' => ''], ['account_id' => '', 'debit' => '', 'credit' => '', 'description' => '']]); @endphp
<div class="page-heading"><div><h1>New journal</h1><p>Debits and credits must be equal. Use it for opening balances (against Opening balance equity), depreciation, accruals and corrections.</p></div></div>
@error('journal')<div class="alert alert-error">{{ $message }}</div>@enderror
<form method="POST" action="{{ route('ledger.journals.store') }}" x-data="journalLines(@js($start))">@csrf
<div class="panel"><div class="panel-body form-grid">
    <label>Date<input type="date" name="journal_date" value="{{ old('journal_date', today()->toDateString()) }}" required></label>
    <label>Memo<input name="memo" value="{{ old('memo') }}" maxlength="300" required placeholder="Opening balances, depreciation for October…"></label>
</div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th style="width:38%">Account</th><th>Detail</th><th class="num">Debit</th><th class="num">Credit</th><th></th></tr></thead><tbody>
<template x-for="(l, i) in lines" :key="i"><tr>
    <td><select :name="`lines[${i}][account_id]`" x-model="l.account_id" style="width:100%"><option value="">—</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->label() }}</option>@endforeach</select></td>
    <td><input :name="`lines[${i}][description]`" x-model="l.description" maxlength="300" style="width:100%"></td>
    <td class="num"><input type="number" step="0.01" min="0" :name="`lines[${i}][debit]`" x-model="l.debit" @input="if (Number(l.debit)) l.credit = ''" style="width:120px"></td>
    <td class="num"><input type="number" step="0.01" min="0" :name="`lines[${i}][credit]`" x-model="l.credit" @input="if (Number(l.credit)) l.debit = ''" style="width:120px"></td>
    <td><button type="button" class="btn btn-small" @click="lines.splice(i, 1)" x-show="lines.length > 2" aria-label="Remove line">✕</button></td>
</tr></template>
</tbody><tfoot><tr><th colspan="2" class="num">Total</th><th class="num" x-text="money(debits)"></th><th class="num" x-text="money(credits)"></th><td></td></tr>
<tr x-show="Math.abs(debits - credits) > 0.004"><td colspan="5" class="num" style="color:var(--danger)">Out by <span x-text="money(Math.abs(debits - credits))"></span></td></tr></tfoot></table></div>
<div class="panel-body"><button type="button" class="btn btn-small" @click="lines.push({account_id: '', debit: '', credit: '', description: ''})">+ Add a line</button></div></div>
<div class="panel"><div class="panel-body"><button class="btn btn-primary" type="submit" :disabled="Math.abs(debits - credits) > 0.004 || debits <= 0">Post journal</button> <a class="btn" href="{{ route('ledger.journals.index') }}">Cancel</a></div></div>
</form>
<script>
function journalLines(start) {
    return {
        lines: start,
        get debits() { return this.lines.reduce((n, l) => n + (Number(l.debit) || 0), 0); },
        get credits() { return this.lines.reduce((n, l) => n + (Number(l.credit) || 0), 0); },
        money(v) { return (Math.round(v * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
</script>
@endsection
