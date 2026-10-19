@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1>Record material lot</h1>
        <p>{{ $material->code }} — {{ $material->name }}</p>
    </div>
</div>

<form method="POST" action="{{ route('materials.lots.store', $material) }}">
    @csrf

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
        <div>
            <label for="lot_number">Internal lot number *</label>
            <input id="lot_number" name="lot_number" class="form-control" required
                   value="{{ old('lot_number') }}">
        </div>
        @if($suppliers->isNotEmpty())
        <div>
            <label for="supplier_id">Approved supplier</label>
            <select id="supplier_id" name="supplier_id" class="form-control">
                <option value="">— none —</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id') === $supplier->id)>{{ $supplier->code }} — {{ $supplier->name }}@if($supplier->approval_status !== 'APPROVED') ({{ \App\Domains\Purchasing\Models\Supplier::STATUSES[$supplier->approval_status] }})@endif</option>
                @endforeach
            </select>
        </div>
        @endif
        <div>
            <label for="supplier_lot_number">Supplier lot / batch number</label>
            <input id="supplier_lot_number" name="supplier_lot_number" class="form-control"
                   value="{{ old('supplier_lot_number') }}">
        </div>
        <div>
            <label for="supplier_name_snapshot">Supplier name</label>
            <input id="supplier_name_snapshot" name="supplier_name_snapshot" class="form-control"
                   value="{{ old('supplier_name_snapshot') }}">
        </div>
        <div>
            <label for="received_date">Received date</label>
            <input id="received_date" name="received_date" type="date" class="form-control"
                   value="{{ old('received_date', now()->toDateString()) }}">
        </div>
        <div>
            <label for="manufacture_date">Manufacture date</label>
            <input id="manufacture_date" name="manufacture_date" type="date" class="form-control"
                   value="{{ old('manufacture_date') }}">
        </div>
        <div>
            <label for="expiry_date">Expiry date</label>
            <input id="expiry_date" name="expiry_date" type="date" class="form-control"
                   value="{{ old('expiry_date') }}">
        </div>
        <div>
            <label for="received_qty">Received quantity ({{ $material->base_uom }}) *</label>
            <input id="received_qty" name="received_qty" type="number" min="0" step="0.001"
                   class="form-control" required value="{{ old('received_qty', '0') }}">
        </div>
        <div>
            <label for="accepted_qty">Accepted quantity ({{ $material->base_uom }}) *</label>
            <input id="accepted_qty" name="accepted_qty" type="number" min="0" step="0.001"
                   class="form-control" required value="{{ old('accepted_qty', '0') }}">
        </div>
        <div>
            <label for="unit_cost">Cost per {{ $material->base_uom }}</label>
            <input id="unit_cost" name="unit_cost" type="number" min="0" step="0.0001"
                   class="form-control" value="{{ old('unit_cost') }}">
        </div>
        <div>
            <label for="currency_code">Currency *</label>
            <input id="currency_code" name="currency_code" class="form-control" required maxlength="3"
                   value="{{ old('currency_code', 'PKR') }}">
        </div>
        <div>
            <label for="status">Lot status *</label>
            <select id="status" name="status" class="form-control" required>
                @foreach(['QUARANTINE','ACCEPTED','REJECTED','CLOSED'] as $status)
                    <option value="{{ $status }}" @selected(old('status', 'QUARANTINE') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="heat_or_batch_reference">Heat / batch reference</label>
            <input id="heat_or_batch_reference" name="heat_or_batch_reference" class="form-control"
                   value="{{ old('heat_or_batch_reference') }}">
        </div>
    </div>

    <div style="margin-top:16px">
        <label for="certificate_reference">Certificate reference / details</label>
        <textarea id="certificate_reference" name="certificate_reference" class="form-control" rows="2">{{ old('certificate_reference') }}</textarea>
    </div>
    <div style="margin-top:16px;margin-bottom:16px">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
    </div>

    <p><strong>Note:</strong> Saving this form records the lot details; it does not post inventory or create a cost layer.</p>

    <button class="btn btn-primary" type="submit">Save lot</button>
    <a class="btn btn-secondary" href="{{ route('materials.show', $material) }}">Cancel</a>
</form>
@endsection
