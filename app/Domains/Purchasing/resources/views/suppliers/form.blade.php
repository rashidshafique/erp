@extends('layouts.app')

@section('title', $supplier->exists ? 'Edit '.$supplier->code : 'Add supplier')
@section('breadcrumb')<a href="{{ route('suppliers.index') }}">Suppliers</a> @if ($supplier->exists)/ <a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->code }}</a> / Edit @else / New @endif @endsection

@section('content')
<div class="page-heading">
    <div><h1>{{ $supplier->exists ? 'Edit '.$supplier->code : 'Add supplier' }}</h1><p>Approval status is set by recording an evaluation on the supplier's page, not here.</p></div>
    <div class="actions"><a class="btn" href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}">Cancel</a></div>
</div>
@php $v = fn ($f) => old($f, $supplier->{$f}); @endphp
<form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}">
    @csrf @if ($supplier->exists) @method('PUT') @endif
    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="code">Code</label><input id="code" name="code" maxlength="40" value="{{ $v('code') }}" placeholder="{{ $supplier->exists ? '' : 'Leave blank to generate: '.app(\App\Services\Codes\CodeGenerator::class)->preview('supplier') }}" style="text-transform:uppercase"></div>
        <div class="field"><label for="name">Name *</label><input id="name" name="name" required maxlength="150" value="{{ $v('name') }}"></div>
        <div class="field"><label for="supplies">What they supply</label><input id="supplies" name="supplies" maxlength="200" value="{{ $v('supplies') }}" placeholder="e.g. pig iron, ferro-silicon, bentonite"></div>
        <div class="field"><label for="contact_name">Contact person</label><input id="contact_name" name="contact_name" maxlength="150" value="{{ $v('contact_name') }}"></div>
        <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" maxlength="60" value="{{ $v('phone') }}"></div>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" maxlength="150" value="{{ $v('email') }}"></div>
        <div class="field"><label for="review_interval_days">Review interval (days) *</label><input id="review_interval_days" type="number" name="review_interval_days" min="30" max="1825" required value="{{ $v('review_interval_days') }}"><div class="help">How often the supplier is re-evaluated. 365 for a year.</div></div>
        <div class="field field-full"><label for="billing_address">Address</label><textarea id="billing_address" name="billing_address" rows="2" maxlength="1000">{{ $v('billing_address') }}</textarea></div>
        <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="3" maxlength="2000">{{ $v('notes') }}</textarea></div>
    </div></div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save supplier</button></div>
</form>
@endsection
