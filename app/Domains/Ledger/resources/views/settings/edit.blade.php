@extends('layouts.app')
@section('title', 'Ledger accounts')
@section('content')
@php $options = fn (?string $selected) => $accounts->map(fn ($a) => '<option value="'.e($a->id).'"'.($selected === $a->id ? ' selected' : '').'>'.e($a->label()).'</option>')->implode(''); @endphp
<div class="page-heading"><div><h1>Ledger accounts</h1><p>Which account each automatic posting uses. Invoices, credit notes, bills, receipts and payments post as soon as they count; a void posts the reversal.</p></div>
<div class="actions"><a class="btn" href="{{ route('ledger.accounts.index') }}">Chart of accounts</a></div></div>
@error('ledger')<div class="alert alert-error">{{ $message }}</div>@enderror
<form method="POST" action="{{ route('ledger.settings.update') }}">@csrf @method('PUT')
<div class="panel"><div class="panel-header"><h2>Postings</h2></div><div class="panel-body form-grid">
@foreach (\App\Domains\Ledger\Services\LedgerSettings::ROLES as $role => [$label])
    <label>{{ $label }}<select name="roles[{{ $role }}]" required>{!! $options($chosen[$role]) !!}</select></label>
@endforeach
</div></div>
<div class="panel"><div class="panel-header"><h2>Payment methods</h2></div><div class="panel-body"><p class="muted small">Receipts and payments by "Cash" go to the cash account and anything else to the bank account above, unless the method is named here. Spelling is matched without case.</p>
@foreach (array_merge(collect($methods)->map(fn ($id, $name) => ['name' => $name, 'account_id' => $id])->values()->all(), array_fill(0, 3, ['name' => '', 'account_id' => null])) as $i => $row)
<div style="display:flex;gap:8px;margin-bottom:6px;flex-wrap:wrap"><input name="methods[{{ $i }}][name]" value="{{ $row['name'] }}" placeholder="Method, e.g. Cheque" maxlength="30" style="width:200px"><select name="methods[{{ $i }}][account_id]"><option value="">—</option>{!! $options($row['account_id']) !!}</select></div>
@endforeach
</div></div>
@if ($authorities->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Tax owed to each authority</h2></div><div class="panel-body form-grid">
@foreach ($authorities as $authority)
    <label>{{ $authority->code }} · {{ $authority->name }}<select name="tax[{{ $authority->code }}]"><option value="">Tax on invoices account</option>{!! $options($taxMap[$authority->code] ?? null) !!}</select></label>
@endforeach
</div></div>
@endif
<div class="panel"><div class="panel-header"><h2>Closing</h2></div><div class="panel-body form-grid">
    <label>Books closed to<input type="date" name="closed_to" value="{{ $closedTo }}"><span class="muted small">Nothing can be posted on or before this date, by hand or by a document. Leave empty while the period is open.</span></label>
</div><div class="panel-body"><button class="btn btn-primary">Save</button></div></div>
</form>
<div class="panel"><div class="panel-header"><h2>Documents from before the ledger</h2></div><div class="panel-body"><form method="POST" action="{{ route('ledger.settings.post-missing') }}">@csrf
<p class="muted small">Posts every issued invoice and credit note, posted bill, receipt and supplier payment that has no journal yet, each on its own date. Safe to run again.</p>
<button class="btn" onclick="return confirm('Post every document not yet in the ledger?')">Post what is missing</button></form></div></div>
@endsection
