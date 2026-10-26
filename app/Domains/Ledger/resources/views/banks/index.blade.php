@extends('layouts.app')
@section('title', 'Bank accounts')
@section('content')
@php $bal = fn ($v) => \App\Support\Formats::number(abs($v), 2).($v < 0 ? ' overdrawn' : ''); @endphp
<div class="page-heading"><div><h1>Bank accounts</h1><p>Each bank sits on a ledger account. Reconcile its statements by ticking the lines that cleared; the books are never changed by it.</p></div></div>
@error('bank')<div class="alert alert-error">{{ $message }}</div>@enderror
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Bank</th><th>Account number</th><th>Ledger account</th><th class="num">Balance in the books</th><th>Reconciled to</th><th class="num">Not yet cleared</th><th></th></tr></thead><tbody>
@forelse ($banks as $b)
<tr @class(['muted' => ! $b->is_active])><td><a href="{{ route('ledger.banks.show', $b) }}">{{ $b->bank_name }}</a>@if ($b->branch) <span class="muted small">{{ $b->branch }}</span>@endif</td><td class="mono">{{ $b->account_number }}</td>
<td class="small"><a href="{{ route('ledger.accounts.show', $b->account) }}">{{ $b->account->label() }}</a></td><td class="num">{{ $bal($b->book) }}</td>
<td>@if ($b->lastReconciled){{ $b->lastReconciled->statement_date->displayShortDate() }} · {{ \App\Support\Formats::number($b->lastReconciled->statement_balance, 2) }}@else<span class="muted">Never</span>@endif</td>
<td class="num">{{ $b->uncleared }}</td>
<td>@if ($b->draft)<a class="btn btn-small" href="{{ route('ledger.reconciliations.show', $b->draft) }}">Continue statement</a>@else<a class="btn btn-small" href="{{ route('ledger.banks.show', $b) }}">Reconcile</a>@endif</td></tr>
@empty<tr><td colspan="7" class="muted">No bank accounts yet. Add one below.</td></tr>@endforelse
</tbody></table></div></div>
<div class="panel" x-data="{ existing: {{ old('account_id') || $accounts->isNotEmpty() ? 'true' : 'false' }} }"><div class="panel-header"><h2>Add a bank account</h2></div>
<form method="POST" action="{{ route('ledger.banks.store') }}">@csrf
<div class="panel-body form-grid">
    <label>Bank<input name="bank_name" value="{{ old('bank_name') }}" maxlength="100" required placeholder="Meezan Bank"></label>
    <label>Branch<input name="branch" value="{{ old('branch') }}" maxlength="100"></label>
    <label>Account title<input name="account_title" value="{{ old('account_title') }}" maxlength="150"></label>
    <label>Account number<input name="account_number" value="{{ old('account_number') }}" maxlength="50"></label>
    <label>IBAN<input name="iban" value="{{ old('iban') }}" maxlength="40"></label>
    <label>Ledger account
        <select name="account_id" x-show="existing" :disabled="!existing"><option value="">—</option>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('account_id') === $a->id)>{{ $a->label() }}</option>@endforeach</select>
        <input name="new_code" x-show="!existing" :disabled="existing" value="{{ old('new_code') }}" maxlength="20" placeholder="Code for the new account, e.g. 1122">
        <span class="small"><a href="#" @click.prevent="existing = !existing" x-text="existing ? 'Or add a new ledger account under the bank group' : 'Or use an existing ledger account'"></a></span>
    </label>
</div>
<div class="panel-body"><button class="btn btn-primary">Add bank account</button></div></form></div>
@endsection
