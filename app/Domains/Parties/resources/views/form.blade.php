@extends('layouts.app')

@section('title', $party->exists ? 'Edit '.$party->code : 'Add partner')
@section('breadcrumb')<a href="{{ route('parties.index') }}">Business partners</a> @if ($party->exists)/ <a href="{{ route('parties.show', $party) }}">{{ $party->code }}</a> / Edit @else / New @endif @endsection

@section('content')
<div class="page-heading">
    <div><h1>{{ $party->exists ? 'Edit '.$party->code : 'Add partner' }}</h1><p>General data shared by every role. Role details (payment terms, approval) are kept on the customer and supplier pages.</p></div>
    <div class="actions"><a class="btn" href="{{ $party->exists ? route('parties.show', $party) : route('parties.index') }}">Cancel</a></div>
</div>
@if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@php $v = fn ($f) => old($f, $party->{$f}); @endphp
<form method="POST" action="{{ $party->exists ? route('parties.update', $party) : route('parties.store') }}">
    @csrf @if ($party->exists) @method('PUT') @endif
    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="name">Name *</label><input id="name" name="name" required maxlength="200" value="{{ $v('name') }}"></div>
        <div class="field"><label for="short_name">Short name</label><input id="short_name" name="short_name" maxlength="60" value="{{ $v('short_name') }}"></div>
        <div class="field"><label for="contact_name">Contact</label><input id="contact_name" name="contact_name" maxlength="150" value="{{ $v('contact_name') }}"></div>
        <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" maxlength="60" value="{{ $v('phone') }}"></div>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" maxlength="160" value="{{ $v('email') }}"></div>
        <div class="field"><label for="tax_number">Tax number</label><input id="tax_number" name="tax_number" maxlength="60" value="{{ $v('tax_number') }}"></div>
        <div class="field field-full"><label for="billing_address">Billing address</label><textarea id="billing_address" name="billing_address" rows="2">{{ $v('billing_address') }}</textarea></div>
        <div class="field field-full"><label for="shipping_address">Shipping address</label><textarea id="shipping_address" name="shipping_address" rows="2">{{ $v('shipping_address') }}</textarea></div>
        @if ($party->exists)
            <div class="field"><label><input type="checkbox" name="active" value="1" @checked($party->active)> Active</label></div>
        @else
            <div class="field"><label>Roles</label>
                <label><input type="checkbox" name="role_customer" value="1" @checked(old('role_customer'))> Customer</label>
                <label><input type="checkbox" name="role_supplier" value="1" @checked(old('role_supplier'))> Supplier</label>
            </div>
        @endif
    </div></div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save partner</button></div>
</form>
@endsection
