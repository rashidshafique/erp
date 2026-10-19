@extends('layouts.app')

@section('title', "New Production Order")
@section('breadcrumb', "New Production Order")

@push('styles')
<style>
.legacy-page .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.legacy-page label { display: block; font-weight: 600; font-size: 14px; }
.legacy-page input,
.legacy-page select,
.legacy-page textarea { box-sizing: border-box; width: 100%; padding: 11px; margin-top: 7px; border: 1px solid #ccd4dd; border-radius: 6px; font: inherit; }
.legacy-page textarea { min-height: 90px; }
.legacy-page .field { margin-bottom: 18px; }
.legacy-page .wide { grid-column: 1 / -1; }
.legacy-page button,
.legacy-page a { display: inline-block; padding: 11px 16px; border-radius: 6px; border: 0; text-decoration: none; cursor: pointer; font: inherit; }
.legacy-page button { background: var(--accent); color: white; }
.legacy-page a { color: var(--accent); }
.legacy-page .error { color: #a12626; font-size: 13px; margin-top: 5px; }
@media(max-width: 768px) {
.legacy-page .grid { grid-template-columns: 1fr; }
.legacy-page .wide { grid-column: auto; }
}
</style>
@endpush

@section('content')
<div class="legacy-page">
<div class="legacy-main">
    <h1>New production order</h1>
    <p>Create a draft first. Release it only when it is ready for the shop floor.</p>


    <form method="POST" action="{{ route('production-orders.store') }}">
        @csrf

        <div class="grid">
            <div class="field">
                <label for="production_order_number">Order number</label>
                <input id="production_order_number"
                       value="Generated automatically when saved"
                       readonly aria-readonly="true" autofocus>
                <small>The next available PO number will be assigned on save.</small>
            </div>

            <div class="field">
                <label for="order_type">Order type *</label>
                <select id="order_type" name="order_type" required>
                    @foreach(['CUSTOMER', 'STOCK', 'REWORK'] as $type)
                        <option value="{{ $type }}" @selected(old('order_type', 'CUSTOMER') === $type)>
                            {{ $type }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field wide">
                <label for="product_revision_id">Product / revision *</label>
                <select id="product_revision_id" name="product_revision_id" required>
                    <option value="">Choose product revision</option>
                    @foreach($products as $product)
                        @foreach($product->revisions as $revision)
                            @if(in_array($revision->status, ['APPROVED', 'ACTIVE'], true))
                                <option value="{{ $revision->id }}"
                                    @selected(old('product_revision_id') === $revision->id)>
                                    {{ $product->code }} — {{ $product->name }}
                                    | Revision {{ $revision->revision }}
                                    | {{ $revision->net_weight_kg }} kg
                                </option>
                            @endif
                        @endforeach
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="planned_qty">Planned quantity *</label>
                <input id="planned_qty" type="number" name="planned_qty"
                       min="0.001" step="0.001" value="{{ old('planned_qty') }}" required>
            </div>

            <div class="field">
                <label for="uom">Unit *</label>
                <select id="uom" name="uom" required>
                    @foreach(['piece', 'kg'] as $unit)
                        <option value="{{ $unit }}" @selected(old('uom', 'piece') === $unit)>
                            {{ $unit }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="priority">Priority *</label>
                <select id="priority" name="priority" required>
                    @foreach(['LOW', 'NORMAL', 'HIGH', 'URGENT'] as $priority)
                        <option value="{{ $priority }}" @selected(old('priority', 'NORMAL') === $priority)>
                            {{ $priority }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="required_date">Required date</label>
                <input id="required_date" type="date" name="required_date"
                       value="{{ old('required_date') }}">
            </div>

            <div class="field">
                <label for="planned_start_date">Planned start</label>
                <input id="planned_start_date" type="date" name="planned_start_date"
                       value="{{ old('planned_start_date') }}">
            </div>

            <div class="field">
                <label for="planned_finish_date">Planned end</label>
                <input id="planned_finish_date" type="date" name="planned_finish_date"
                       value="{{ old('planned_finish_date') }}">
            </div>

            <div class="field wide">
                <label for="instructions">Production instructions</label>
                <textarea id="instructions" name="instructions">{{ old('instructions') }}</textarea>
            </div>

            <div class="field wide">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
            </div>
        </div>

        <button type="submit">Save draft order</button>
        <a href="{{ route('production-orders.index') }}">Cancel</a>
    </form>
</div>
</div>
@endsection
