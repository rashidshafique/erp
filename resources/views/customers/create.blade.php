@extends('layouts.app')

@section('title', 'New customer')
@section('breadcrumb', 'Customers / New')

@section('content')
<div class="page-heading"><div><h1>New customer</h1><p>Create the account before entering the first order.</p></div></div>


<form method="POST" action="{{ route('customers.store') }}">
@csrf
<div class="panel"><div class="panel-body"><div class="form-grid" data-progressive="3">
    <div class="field"><label for="code">Code</label><input id="code" name="code" maxlength="40" placeholder="Leave blank to generate: {{ app(\App\Services\Codes\CodeGenerator::class)->preview('customer') }}" value="{{ old('code') }}"></div>
    <div class="field"><label for="name">Name *</label><input id="name" name="name" required maxlength="200" value="{{ old('name') }}"></div>
    <div class="field"><label for="short_name">Short name</label><input id="short_name" name="short_name" maxlength="60" value="{{ old('short_name') }}"></div>
    <div class="field"><label for="contact_name">Contact</label><input id="contact_name" name="contact_name" maxlength="120" value="{{ old('contact_name') }}"></div>
    <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" maxlength="40" value="{{ old('phone') }}"></div>
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="160" value="{{ old('email') }}"></div>
    <div class="field"><label for="tax_number">Tax number</label><input id="tax_number" name="tax_number" maxlength="60" value="{{ old('tax_number') }}"></div>
    <div class="field"><label for="payment_terms">Payment terms</label><input id="payment_terms" name="payment_terms" maxlength="100" value="{{ old('payment_terms') }}"></div>
    <div class="field field-full"><label for="billing_address">Billing address</label><textarea id="billing_address" name="billing_address" rows="2">{{ old('billing_address') }}</textarea></div>
    <div class="field field-full"><label for="shipping_address">Shipping address</label><textarea id="shipping_address" name="shipping_address" rows="2">{{ old('shipping_address') }}</textarea></div>
</div></div></div>
<button class="btn btn-primary" type="submit">Save customer</button>
</form>
@endsection
