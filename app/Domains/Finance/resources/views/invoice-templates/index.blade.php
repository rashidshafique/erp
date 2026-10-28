@extends('layouts.app')
@section('title', 'Invoice templates')
@section('content')
@php $ready = app(\App\Domains\Finance\Services\InvoiceTemplates::class)->options(); @endphp
<div class="page-heading"><div><h1>Invoice templates</h1><p>How an invoice or credit note prints. Pick one per invoice on its page, or set the default under Settings &gt; Invoicing &amp; tax.</p></div>
<div class="actions"><a class="btn btn-primary" href="{{ route('invoice-templates.create') }}">+ New template</a></div></div>
@error('template')<div class="alert alert-error">{{ $message }}</div>@enderror
<div class="panel"><div class="panel-header"><h2>Ready-made</h2></div><div class="table-wrap"><table><thead><tr><th>Name</th><th>What it is</th><th></th></tr></thead><tbody>
    <tr><td>Standard invoice @if ($default === 'STANDARD')<span class="muted small">(default)</span>@endif</td><td>The original layout. It cannot be copied, but the two below can.</td><td></td></tr>
    <tr><td>Sales tax invoice @if ($default === 'TAX')<span class="muted small">(default)</span>@endif</td><td>Registrations, tariff codes, tax per line, each tax listed.</td><td class="actions"><a class="btn btn-sm" href="{{ route('invoice-templates.create', ['from' => 'TAX']) }}">Copy to edit</a></td></tr>
    <tr><td>Simple invoice @if ($default === 'SIMPLE')<span class="muted small">(default)</span>@endif</td><td>Description, quantity, price, discount and amount.</td><td class="actions"><a class="btn btn-sm" href="{{ route('invoice-templates.create', ['from' => 'SIMPLE']) }}">Copy to edit</a></td></tr>
</tbody></table></div></div>
<div class="panel"><div class="panel-header"><h2>Your templates</h2></div><div class="table-wrap"><table><thead><tr><th>Name</th><th>For</th><th>Status</th><th></th></tr></thead><tbody>
@forelse ($templates as $t)
    <tr><td><a href="{{ route('invoice-templates.edit', $t) }}">{{ $t->name }}</a> @if ($default === $t->key())<span class="muted small">(default)</span>@endif</td><td>{{ \App\Domains\Finance\Models\InvoiceTemplate::APPLIES[$t->applies_to] ?? $t->applies_to }}</td><td>{{ $t->is_active ? 'On' : 'Switched off' }}</td>
    <td class="actions"><a class="btn btn-sm" href="{{ route('invoice-templates.edit', $t) }}">Edit</a> <a class="btn btn-sm" href="{{ route('invoice-templates.create', ['from' => $t->key()]) }}">Copy</a>
        <form method="POST" action="{{ route('invoice-templates.toggle', $t) }}" style="display:inline">@csrf<button class="btn btn-sm">{{ $t->is_active ? 'Switch off' : 'Switch on' }}</button></form>
        <form method="POST" action="{{ route('invoice-templates.destroy', $t) }}" style="display:inline" onsubmit="return confirm('Delete {{ $t->name }}?')">@csrf @method('DELETE')<button class="btn btn-sm" @disabled($inUse->has($t->key())) title="{{ $inUse->has($t->key()) ? 'Invoices use it: switch it off instead' : '' }}">Delete</button></form></td></tr>
@empty<tr><td colspan="4" class="muted">None yet. Copy a ready-made one to start.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
