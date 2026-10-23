@extends('layouts.app')

@section('title', 'Record lot')
@section('breadcrumb')<a href="{{ route('materials.index') }}">Materials</a> / <a href="{{ route('materials.show', $material) }}">{{ $material->code }}</a> / New lot @endsection

@section('content')
<div class="page-heading">
    <div>
        <h1>Record material lot</h1>
        <p>{{ $material->code }} — {{ $material->name }}</p>
    </div>
</div>

<form method="POST" action="{{ route('materials.lots.store', $material) }}">
    @csrf

    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field">
            <label for="lot_number">Internal lot number *</label>
            <input id="lot_number" name="lot_number" required
                   value="{{ old('lot_number') }}">
        </div>
        @if($suppliers->isNotEmpty())
        <div class="field">
            <label for="supplier_id">Approved supplier</label>
            <select id="supplier_id" name="supplier_id">
                <option value="">— none —</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id') === $supplier->id)>{{ $supplier->code }} — {{ $supplier->name }}@if($supplier->approval_status !== 'APPROVED') ({{ \App\Domains\Purchasing\Models\Supplier::STATUSES[$supplier->approval_status] }})@endif</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="field">
            <label for="supplier_lot_number">Supplier lot / batch number</label>
            <input id="supplier_lot_number" name="supplier_lot_number"
                   value="{{ old('supplier_lot_number') }}">
        </div>
        <div class="field">
            <label for="supplier_name_snapshot">Supplier name</label>
            <input id="supplier_name_snapshot" name="supplier_name_snapshot"
                   value="{{ old('supplier_name_snapshot') }}">
        </div>
        <div class="field">
            <label for="received_date">Received date</label>
            <input id="received_date" name="received_date" type="date"
                   value="{{ old('received_date', now()->toDateString()) }}">
        </div>
        <div class="field">
            <label for="manufacture_date">Manufacture date</label>
            <input id="manufacture_date" name="manufacture_date" type="date"
                   value="{{ old('manufacture_date') }}">
        </div>
        <div class="field">
            <label for="expiry_date">Expiry date</label>
            <input id="expiry_date" name="expiry_date" type="date"
                   value="{{ old('expiry_date') }}">
        </div>
        <div class="field">
            <label for="received_qty">Received quantity ({{ $material->base_uom }}) *</label>
            <input id="received_qty" name="received_qty" type="number" min="0" step="0.001"
                   required value="{{ old('received_qty', '0') }}">
        </div>
        <div class="field">
            <label for="accepted_qty">Accepted quantity ({{ $material->base_uom }}) *</label>
            <input id="accepted_qty" name="accepted_qty" type="number" min="0" step="0.001"
                   required value="{{ old('accepted_qty', '0') }}">
        </div>
        <div class="field">
            <label for="unit_cost">Cost per {{ $material->base_uom }}</label>
            <input id="unit_cost" name="unit_cost" type="number" min="0" step="0.0001"
                   value="{{ old('unit_cost') }}">
        </div>
        <div class="field">
            <label for="currency_code">Currency *</label>
            <input id="currency_code" name="currency_code" required maxlength="3"
                   value="{{ old('currency_code', 'PKR') }}">
        </div>
        <div class="field">
            <label for="status">Lot status *</label>
            <select id="status" name="status" required>
                @foreach(['QUARANTINE','ACCEPTED','REJECTED','CLOSED'] as $status)
                    <option value="{{ $status }}" @selected(old('status', 'QUARANTINE') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="heat_or_batch_reference">Heat / batch reference</label>
            <input id="heat_or_batch_reference" name="heat_or_batch_reference"
                   value="{{ old('heat_or_batch_reference') }}">
        </div>
        <div class="field field-full">
            <label for="certificate_reference">Certificate reference / details</label>
            <textarea id="certificate_reference" name="certificate_reference" rows="2">{{ old('certificate_reference') }}</textarea>
        </div>
        <div class="field field-full">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
        </div>
    </div></div></div>

    <p class="muted">Saving this form records the lot details; it does not post inventory or create a cost layer. Post the receipt from the material page.</p>

    <div class="actions"><button class="btn btn-primary" type="submit">Save lot</button>
    <a class="btn" href="{{ route('materials.show', $material) }}">Cancel</a></div>
</form>
@endsection
