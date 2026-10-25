
@extends('layouts.app')

@section('title', 'Edit '.$product->code)
@section('breadcrumb')
<a href="{{ route('products.index') }}">Products</a> /
<a href="{{ route('products.show', $product) }}">{{ $product->code }}</a> / Edit
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h1>Edit product</h1>
        <p>Update the product master fields. Existing revisions are retained.</p>
    </div>
    <a class="btn" href="{{ route('products.show', $product) }}">Cancel</a>
</div>

<div class="panel">
    <div class="panel-header"><h2>{{ $product->code }}</h2></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('products.update', $product) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field">
                    <label for="code">Product code <span class="required">*</span></label>
                    <input id="code" name="code" maxlength="40" required
                           value="{{ old('code', $product->code) }}">
                    @error('code')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="name">Product name <span class="required">*</span></label>
                    <input id="name" name="name" maxlength="255" required
                           value="{{ old('name', $product->name) }}">
                    @error('name')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="product_type">Product type <span class="required">*</span></label>
                    <select id="product_type" name="product_type" required>
                        @foreach (['CASTING', 'CORE', 'OTHER'] as $type)
                            <option value="{{ $type }}" @selected(old('product_type', $product->product_type) === $type)>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="base_uom">Base unit <span class="required">*</span></label>
                    <select id="base_uom" name="base_uom" required>
                        @foreach (['piece', 'kg', 'set'] as $uom)
                            <option value="{{ $uom }}" @selected(old('base_uom', $product->base_uom) === $uom)>
                                {{ $uom }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="nominal_weight_kg">Nominal weight (kg)</label>
                    <input id="nominal_weight_kg" name="nominal_weight_kg" type="number"
                           step="any" min="0"
                           value="{{ old('nominal_weight_kg', $product->nominal_weight_kg) }}">
                    @error('nominal_weight_kg')<div class="error-text">{{ $message }}</div>@enderror
                    <div class="help">{{ $product->currentRevision() ? 'This is the net weight of revision '.$product->currentRevision()->revision.', the one the product is made to now. Changing it changes that revision.' : 'The first revision starts with this weight.' }}</div>
                </div>

                <div class="field">
                    <label for="active">Product status <span class="required">*</span></label>
                    <select id="active" name="active" required>
                        <option value="1" @selected((string) old('active', (int) $product->active) === '1')>Active</option>
                        <option value="0" @selected((string) old('active', (int) $product->active) === '0')>Inactive</option>
                    </select>
                </div>

                <div class="field field-full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description">{{ old('description', $product->description) }}</textarea>
                </div>
            </div>

            <h3 style="margin:22px 0 10px">Tax and reporting</h3>
            @include('partials.trade-fields', ['item' => $product])

            <div class="actions" style="margin-top:20px">
                <button class="btn btn-primary" type="submit">Save changes</button>
                <a class="btn" href="{{ route('products.show', $product) }}">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
