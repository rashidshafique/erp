@extends('layouts.app')

@section('title', 'New product')
@section('breadcrumb')
<a href="{{ route('products.index') }}">Products</a> / New product
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h1>New product</h1>
        <p>Create a product and its first engineering revision together.</p>
    </div>
    <a class="btn" href="{{ route('products.index') }}">Cancel</a>
</div>

<form method="POST" action="{{ route('products.store') }}">
    @csrf

    <div class="panel">
        <div class="panel-header"><h2>Product information</h2></div>
        <div class="panel-body">
            <div class="form-grid" data-progressive="3">
                <div class="field">
                    <label for="code">Product code</label>
                    <input id="code" name="code" maxlength="40"
                           value="{{ old('code') }}" placeholder="Leave blank to generate: {{ app(\App\Services\Codes\CodeGenerator::class)->preview('product') }}">
                    <div class="help">Use your established part-number convention.</div>
                    @error('code')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="name">Product name <span class="required">*</span></label>
                    <input id="name" name="name" required maxlength="255"
                           value="{{ old('name') }}" placeholder="e.g. Gas valve body">
                    @error('name')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="product_type">Product type <span class="required">*</span></label>
                    <select id="product_type" name="product_type" required>
                        @foreach (['CASTING' => 'Casting', 'CORE' => 'Core', 'OTHER' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('product_type', 'CASTING') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="base_uom">Base unit of measure <span class="required">*</span></label>
                    <select id="base_uom" name="base_uom" required>
                        @foreach (['piece' => 'Piece', 'kg' => 'Kilogram', 'set' => 'Set'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('base_uom', 'piece') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="nominal_weight_kg">Nominal casting weight (kg)</label>
                    <input id="nominal_weight_kg" type="number" step="0.0001" min="0"
                           name="nominal_weight_kg" value="{{ old('nominal_weight_kg') }}">
                    <div class="help">Nominal finished-product weight, not total metal poured.</div>
                    @error('nominal_weight_kg')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="active">Product status <span class="required">*</span></label>
                    <select id="active" name="active" required>
                        <option value="1" @selected(old('active', '1') === '1')>Active</option>
                        <option value="0" @selected(old('active') === '0')>Inactive</option>
                    </select>
                </div>

                <div class="field field-full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"
                              placeholder="Application, customer, or other identifying details">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Initial product revision</h2></div>
        <div class="panel-body">
            <p class="muted small">
                Each product needs a revision before it can be used in a production order.
                This creates the initial revision with the product.
            </p>
            <div class="form-grid" data-progressive="3">
                <div class="field">
                    <label for="revision">Revision identifier <span class="required">*</span></label>
                    <input id="revision" name="revision" required maxlength="20"
                           value="{{ old('revision', 'A') }}" placeholder="A">
                    @error('revision')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="revision_status">Revision status <span class="required">*</span></label>
                    <select id="revision_status" name="revision_status" required>
                        @foreach ($revisionStatuses as $status)
                            <option value="{{ $status }}" @selected(old('revision_status', 'DRAFT') === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                    <div class="help">Use DRAFT until engineering has reviewed the specification.</div>
                </div>

                <div class="field">
                    <label for="net_weight_kg">Net weight for this revision (kg)</label>
                    <input id="net_weight_kg" type="number" step="0.0001" min="0"
                           name="net_weight_kg" value="{{ old('net_weight_kg') }}">
                </div>

                <div class="field">
                    <label for="effective_from">Effective from</label>
                    <input id="effective_from" type="date" name="effective_from"
                           value="{{ old('effective_from') }}">
                </div>

                <div class="field">
                    <label for="effective_to">Effective to</label>
                    <input id="effective_to" type="date" name="effective_to"
                           value="{{ old('effective_to') }}">
                </div>

                <div class="field field-full">
                    <label for="engineering_notes">Engineering notes</label>
                    <textarea id="engineering_notes" name="engineering_notes"
                              placeholder="Drawing reference, dimensional revision, material specification, or change notes">{{ old('engineering_notes') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit">Save product and revision</button>
        <a class="btn" href="{{ route('products.index') }}">Cancel</a>
    </div>
</form>
@endsection
