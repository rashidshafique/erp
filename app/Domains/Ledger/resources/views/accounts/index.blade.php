@extends('layouts.app')
@section('title', 'Chart of accounts')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number(abs($v), 2).($v > 0 ? ' Dr' : ($v < 0 ? ' Cr' : '')); @endphp
<div class="page-heading"><div><h1>Chart of accounts</h1><p>Groups add up the accounts under them; postings go to accounts with no sub-accounts. Add an account anywhere with “+ sub-account”.</p></div>
<div class="actions"><a class="btn" href="{{ route('ledger.trial-balance') }}">Trial balance</a><a class="btn btn-primary" href="{{ route('ledger.journals.create') }}">+ Journal</a></div></div>
@error('account')<div class="alert alert-error">{{ $message }}</div>@enderror
<div class="panel" x-data="{ adding: null, editing: null }"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Account</th><th>Type</th><th class="num">Balance</th><th></th></tr></thead><tbody>
@foreach ($accounts as $a)
<tr @class(['muted' => ! $a->is_active])>
    <td class="mono">{{ $a->code }}</td>
    <td style="padding-left:{{ 12 + $a->depth * 22 }}px">@if ($a->is_group)<strong>{{ $a->name }}</strong>@else<a href="{{ route('ledger.accounts.show', $a) }}">{{ $a->name }}</a>@endif @unless ($a->is_active)<span class="small">(off)</span>@endunless</td>
    <td class="small">{{ \App\Domains\Ledger\Models\LedgerAccount::TYPES[$a->type] }}</td>
    <td class="num">@if ($a->is_group)<strong>{{ $m($a->net) }}</strong>@else{{ $m($a->net) }}@endif</td>
    <td style="white-space:nowrap"><button type="button" class="btn btn-small" @click="adding = adding === '{{ $a->id }}' ? null : '{{ $a->id }}'; editing = null">+ sub-account</button> <button type="button" class="btn btn-small" @click="editing = editing === '{{ $a->id }}' ? null : '{{ $a->id }}'; adding = null">Edit</button></td>
</tr>
<tr x-show="adding === '{{ $a->id }}'" x-cloak><td colspan="5"><form method="POST" action="{{ route('ledger.accounts.store') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding-left:{{ 12 + ($a->depth + 1) * 22 }}px">@csrf
    <input type="hidden" name="parent_id" value="{{ $a->id }}"><input name="code" placeholder="Code" maxlength="20" required style="width:100px"><input name="name" placeholder="Name of the account under {{ $a->name }}" maxlength="150" required style="min-width:280px;flex:1"><button class="btn btn-primary btn-small">Add</button></form></td></tr>
<tr x-show="editing === '{{ $a->id }}'" x-cloak><td colspan="5"><div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><form method="POST" action="{{ route('ledger.accounts.update', $a) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;flex:1">@csrf @method('PUT')
    <input name="code" value="{{ $a->code }}" maxlength="20" required style="width:100px"><input name="name" value="{{ $a->name }}" maxlength="150" required style="min-width:240px;flex:1"><input name="description" value="{{ $a->description }}" placeholder="Note" maxlength="300">
    <label style="margin:0"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($a->is_active)> on</label><button class="btn btn-primary btn-small">Save</button></form>
    <form method="POST" action="{{ route('ledger.accounts.destroy', $a) }}">@csrf @method('DELETE')<button class="btn btn-small" onclick="return confirm('Delete this account? Only an account with no postings can be deleted.')">Delete</button></form></div></td></tr>
@endforeach
</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('ledger.accounts.store') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">@csrf
    <strong>New top-level account</strong><input name="code" placeholder="Code" maxlength="20" required style="width:100px"><input name="name" placeholder="Name" maxlength="150" required style="min-width:220px">
    <select name="type" required>@foreach (\App\Domains\Ledger\Models\LedgerAccount::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select><button class="btn btn-primary">Add</button></form></div></div>
@endsection
