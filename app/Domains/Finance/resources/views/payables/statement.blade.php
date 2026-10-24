@extends('layouts.app')
@section('title', 'Statement '.$supplier->name)
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>{{ $supplier->name }}</h1><p>Statement of account. A positive balance is what we owe them.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('supplier-payments.create', ['supplier' => $supplier->id]) }}">Record a payment</a> <a class="btn" href="{{ route('bills.create', ['supplier' => $supplier->id]) }}">New bill</a></div></div>
<div class="panel"><div class="panel-body" style="display:flex;gap:28px"><div><div class="muted small">Balance</div><strong>{{ $m($statement['balance']) }}</strong></div><div><div class="muted small">Open bills</div>{{ $m($open->sum(fn ($i) => $i->balance())) }}</div><div><div class="muted small">Advances paid</div>{{ $m($advance) }}</div></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Document</th><th class="num">Billed</th><th class="num">Paid / credited</th><th class="num">Balance</th></tr></thead><tbody>
@forelse ($statement['rows'] as $e)<tr><td>{{ $e['date']->displayShortDate() }}</td><td>{{ $e['type'] }} <a href="{{ route($e['type'] === 'Payment' ? 'supplier-payments.show' : 'bills.show', $e['model']) }}">{{ $e['number'] }}</a></td><td class="num">{{ $e['owed'] > 0 ? $m($e['owed']) : '' }}</td><td class="num">{{ $e['paid'] > 0 ? $m($e['paid']) : '' }}</td><td class="num">{{ $m($e['balance']) }}</td></tr>
@empty<tr><td colspan="5" class="muted">Nothing recorded for this supplier.</td></tr>@endforelse</tbody></table></div></div>
@endsection
