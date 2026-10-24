@extends('layouts.app')
@section('title', 'Statement '.$customer->name)
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>{{ $customer->name }}</h1><p>Statement of account. A positive balance is what they owe us.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('receipts.create', ['customer' => $customer->id]) }}">Record a receipt</a> <a class="btn" href="{{ route('invoices.create', ['customer' => $customer->id]) }}">New invoice</a></div></div>
<div class="panel"><div class="panel-body" style="display:flex;gap:28px"><div><div class="muted small">Balance</div><strong>{{ $m($statement['balance']) }}</strong></div><div><div class="muted small">Open invoices</div>{{ $m($open->sum(fn ($i) => $i->balance())) }}</div><div><div class="muted small">Paid on account</div>{{ $m($onAccount) }}</div></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Document</th><th class="num">Charged</th><th class="num">Paid / credited</th><th class="num">Balance</th></tr></thead><tbody>
@forelse ($statement['rows'] as $e)<tr><td>{{ $e['date']->displayShortDate() }}</td><td>{{ $e['type'] }} <a href="{{ route($e['type'] === 'Receipt' ? 'receipts.show' : 'invoices.show', $e['model']) }}">{{ $e['number'] }}</a></td><td class="num">{{ $e['debit'] > 0 ? $m($e['debit']) : '' }}</td><td class="num">{{ $e['credit'] > 0 ? $m($e['credit']) : '' }}</td><td class="num">{{ $m($e['balance']) }}</td></tr>
@empty<tr><td colspan="5" class="muted">Nothing recorded for this customer.</td></tr>@endforelse</tbody></table></div></div>
@endsection
