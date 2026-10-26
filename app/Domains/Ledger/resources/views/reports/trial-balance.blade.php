@extends('layouts.app')
@section('title', 'Trial balance')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Trial balance</h1><p>Every account's balance as at {{ \Illuminate\Support\Carbon::parse($asAt)->displayDate() }}. Debits and credits always agree; group lines show what their accounts add up to.</p></div></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="as_at">As at</label><input type="date" id="as_at" name="as_at" value="{{ $asAt }}"></div>
    <div class="field"><label><input type="checkbox" name="all" value="1" @checked(request()->boolean('all'))> Accounts with no balance too</label></div>
    <button class="btn">Show</button>
</form></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Account</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>
@forelse ($accounts as $a)
<tr><td class="mono">{{ $a->code }}</td>
@if ($a->is_group)<td style="padding-left:{{ 12 + $a->depth * 22 }}px"><strong>{{ $a->name }}</strong></td><td class="num muted">{{ $a->net > 0 ? $m($a->net) : '' }}</td><td class="num muted">{{ $a->net < 0 ? $m(-$a->net) : '' }}</td>
@else<td style="padding-left:{{ 12 + $a->depth * 22 }}px"><a href="{{ route('ledger.accounts.show', [$a, 'to' => $asAt]) }}">{{ $a->name }}</a></td><td class="num">{{ $a->net > 0 ? $m($a->net) : '' }}</td><td class="num">{{ $a->net < 0 ? $m(-$a->net) : '' }}</td>@endif
</tr>
@empty<tr><td colspan="4" class="muted">Nothing is posted yet.</td></tr>@endforelse
</tbody><tfoot><tr><th colspan="2" class="num">Total</th><th class="num">{{ $m($debits) }}</th><th class="num">{{ $m($credits) }}</th></tr></tfoot></table></div></div>
@endsection
