@extends('layouts.app')
@section('title', $bank->bank_name)
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>{{ $bank->label() }}</h1><p>On <a href="{{ route('ledger.accounts.show', $bank->account) }}">{{ $bank->account->label() }}</a>. Balance in the books {{ $m($book) }}; {{ $uncleared }} {{ Str::plural('line', $uncleared) }} not yet cleared on a statement.</p></div>
<div class="actions"><a class="btn" href="{{ route('ledger.banks.index') }}">Bank accounts</a></div></div>
@error('statement')<div class="alert alert-error">{{ $message }}</div>@enderror
@if ($draft)
<div class="panel"><div class="panel-body">A statement to {{ $draft->statement_date->displayDate() }} is open. <a class="btn btn-primary btn-small" href="{{ route('ledger.reconciliations.show', $draft) }}">Continue</a></div></div>
@else
<div class="panel"><div class="panel-header"><h2>Reconcile a statement</h2></div><form method="POST" action="{{ route('ledger.banks.statements.store', $bank) }}">@csrf
<div class="panel-body form-grid">
    <label>Statement date<input type="date" name="statement_date" value="{{ old('statement_date', today()->toDateString()) }}" required></label>
    <label>Closing balance on the statement<input type="number" step="0.01" name="statement_balance" value="{{ old('statement_balance') }}" required><span class="muted small">Opens on {{ $m($last?->statement_balance ?? 0) }}@if ($last), the balance on {{ $last->statement_date->displayDate() }}@endif. An overdraft is a negative balance.</span></label>
</div><div class="panel-body"><button class="btn btn-primary">Start</button></div></form></div>
@endif
<div class="panel"><div class="panel-header"><h2>Statements</h2></div><div class="table-wrap"><table><thead><tr><th>Statement date</th><th class="num">Opening</th><th class="num">Closing</th><th>Status</th><th>By</th><th></th></tr></thead><tbody>
@forelse ($bank->reconciliations as $r)
<tr><td>{{ $r->statement_date->displayDate() }}</td><td class="num">{{ $m($r->opening_balance) }}</td><td class="num">{{ $m($r->statement_balance) }}</td>
<td>@if ($r->isDone())<span class="status status-completed">Reconciled</span> <span class="muted small">{{ $r->completed_at?->displayShortDate() }}</span>@else<span class="status status-on-hold">Open</span>@endif</td>
<td class="small">{{ $r->user?->name }}</td><td><a class="btn btn-small" href="{{ route('ledger.reconciliations.show', $r) }}">{{ $r->isDone() ? 'View' : 'Continue' }}</a></td></tr>
@empty<tr><td colspan="6" class="muted">No statement reconciled yet.</td></tr>@endforelse
</tbody></table></div></div>
<div class="panel"><div class="panel-header"><h2>Bank details</h2></div><form method="POST" action="{{ route('ledger.banks.update', $bank) }}">@csrf @method('PUT')
<div class="panel-body form-grid">
    <label>Bank<input name="bank_name" value="{{ $bank->bank_name }}" maxlength="100" required></label>
    <label>Branch<input name="branch" value="{{ $bank->branch }}" maxlength="100"></label>
    <label>Account title<input name="account_title" value="{{ $bank->account_title }}" maxlength="150"></label>
    <label>Account number<input name="account_number" value="{{ $bank->account_number }}" maxlength="50"></label>
    <label>IBAN<input name="iban" value="{{ $bank->iban }}" maxlength="40"></label>
    <label>Notes<input name="notes" value="{{ $bank->notes }}" maxlength="500"></label>
    <label><span><input type="checkbox" name="is_active" value="1" @checked($bank->is_active)> In use</span></label>
</div><div class="panel-body"><button class="btn btn-primary">Save</button></div></form></div>
@endsection
