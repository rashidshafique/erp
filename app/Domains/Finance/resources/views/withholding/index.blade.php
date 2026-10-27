@extends('layouts.app')
@section('title', 'Tax certificates')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Tax certificates</h1><p>Certificates customers give for tax they kept back from our invoices and paid to the authority. Each one settles that part of the invoices it names.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('withholding-certificates.create') }}">+ Record a certificate</a></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Number</th><th>Customer</th><th>Date</th><th>Tax</th><th>Their reference</th><th class="num">Amount</th><th></th></tr></thead><tbody>
@forelse ($certificates as $c)<tr><td><a href="{{ route('withholding-certificates.show', $c) }}">{{ $c->certificate_number }}</a></td><td>{{ $c->customer?->name }}</td><td>{{ $c->certificate_date->displayShortDate() }}</td><td>{{ $c->tax_label }}</td><td>{{ $c->reference }}</td><td class="num">{{ $m($c->amount) }}</td><td>@if ($c->isVoid())<span class="status status-cancelled">VOID</span>@endif</td></tr>
@empty<tr><td colspan="7" class="muted">No certificate yet.</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $certificates->links() }}</div></div>
@endsection
