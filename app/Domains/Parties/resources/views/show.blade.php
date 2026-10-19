@extends('layouts.app')

@section('title', $party->code.' · '.$party->name)
@section('breadcrumb')<a href="{{ route('parties.index') }}">Business partners</a> / {{ $party->code }} @endsection

@section('content')
<div class="page-heading">
    <div><h1>{{ $party->name }}</h1><p><span class="mono">{{ $party->code }}</span> · {{ $party->active ? 'Active' : 'Inactive' }}</p></div>
    <div class="actions"><a class="btn" href="{{ route('parties.index') }}">All partners</a><a class="btn" href="{{ route('parties.edit', $party) }}">Edit</a></div>
</div>

<div class="panel"><div class="panel-header"><h2>General data</h2></div>
<div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px">
    @foreach (['Short name' => $party->short_name, 'Contact' => $party->contact_name, 'Phone' => $party->phone, 'Email' => $party->email, 'Tax number' => $party->tax_number] as $label => $value)
        <div><div class="muted small">{{ $label }}</div><strong>{{ $value ?: '—' }}</strong></div>
    @endforeach
    @if ($party->billing_address)<div style="grid-column:1/-1"><div class="muted small">Billing address</div>{{ $party->billing_address }}</div>@endif
    @if ($party->shipping_address)<div style="grid-column:1/-1"><div class="muted small">Shipping address</div>{{ $party->shipping_address }}</div>@endif
</div></div>

<div class="panel"><div class="panel-header"><h2>Roles</h2></div>
<div class="panel-body">
    <p>
        @if ($party->customer)<strong>Customer</strong> <span class="mono">{{ $party->customer->code }}</span> · {{ $party->customer->payment_terms ?: 'no payment terms' }}@else <span class="muted">Not a customer.</span> @endif
        @unless ($party->customer)<form method="POST" action="{{ route('parties.roles.add', [$party, 'customer']) }}" style="display:inline">@csrf<button class="btn" type="submit">Make a customer</button></form>@endunless
    </p>
    <p>
        @if ($party->supplier)<strong>Supplier</strong> <a class="mono" href="{{ route('suppliers.show', $party->supplier) }}">{{ $party->supplier->code }}</a> · {{ \App\Domains\Purchasing\Models\Supplier::STATUSES[$party->supplier->approval_status] }}@else <span class="muted">Not a supplier.</span> @endif
        @unless ($party->supplier)<form method="POST" action="{{ route('parties.roles.add', [$party, 'supplier']) }}" style="display:inline">@csrf<button class="btn" type="submit">Make a supplier</button></form>@endunless
    </p>
</div></div>
@endsection
