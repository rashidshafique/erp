@include('partials.search-select')
@php($order ??= null)
<section class="panel">
    <div class="panel-header"><h2>Order</h2></div>
    <div class="panel-body">
        <div class="form-grid">
            <div class="field">
                <label for="order_type">Order type <span class="required">*</span></label>
                <select id="order_type" name="order_type" required>
                    @foreach (['CUSTOMER', 'STOCK', 'REWORK'] as $type)
                        <option value="{{ $type }}" @selected(old('order_type', $order?->order_type ?? 'CUSTOMER') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                @error('order_type')<div class="error-text">{{ $message }}</div>@enderror
            </div>

            <div class="field field-full">
                <label for="product_revision_id">Product / revision <span class="required">*</span></label>
                @php($photos = \App\Models\Attachment::coverUrlsFor('product', $products->pluck('id')->all()))
                <select id="product_revision_id" name="product_revision_id" required data-search-select="photos">
                    <option value="">Choose product revision</option>
                    @foreach ($products as $product)
                        @foreach ($product->revisions as $revision)
                            @if (in_array($revision->status, ['APPROVED', 'ACTIVE'], true))
                                <option value="{{ $revision->id }}" data-sub="{{ $product->code }} · rev {{ $revision->revision }}{{ $revision->net_weight_kg ? ' · '.(float) $revision->net_weight_kg.' kg' : '' }}" @isset($photos[$product->id]) data-img="{{ $photos[$product->id] }}" @endisset @selected(old('product_revision_id', $order?->product_revision_id) === $revision->id)>
                                    {{ $product->name }}
                                </option>
                            @endif
                        @endforeach
                    @endforeach
                </select>
                @error('product_revision_id')<div class="error-text">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label for="planned_qty">Planned quantity <span class="required">*</span></label>
                <input id="planned_qty" type="number" name="planned_qty" min="0" step="any" value="{{ old('planned_qty', $order ? (float) $order->planned_qty : null) }}" required>
                @error('planned_qty')<div class="error-text">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label for="uom">Unit <span class="required">*</span></label>
                <select id="uom" name="uom" required>
                    @foreach (['piece', 'kg'] as $unit)
                        <option value="{{ $unit }}" @selected(old('uom', $order?->uom ?? 'piece') === $unit)>{{ $unit }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="priority">Priority <span class="required">*</span></label>
                <select id="priority" name="priority" required>
                    @foreach (['LOW', 'NORMAL', 'HIGH', 'URGENT'] as $priority)
                        <option value="{{ $priority }}" @selected(old('priority', $order?->priority ?? 'NORMAL') === $priority)>{{ $priority }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="required_date">Required date</label>
                <input id="required_date" type="date" name="required_date" value="{{ old('required_date', $order?->required_date?->toDateString()) }}">
            </div>

            <div class="field">
                <label for="planned_start_date">Planned start</label>
                <input id="planned_start_date" type="date" name="planned_start_date" value="{{ old('planned_start_date', $order?->planned_start_date?->toDateString()) }}">
            </div>

            <div class="field">
                <label for="planned_finish_date">Planned end</label>
                <input id="planned_finish_date" type="date" name="planned_finish_date" value="{{ old('planned_finish_date', $order?->planned_finish_date?->toDateString()) }}">
                @error('planned_finish_date')<div class="error-text">{{ $message }}</div>@enderror
            </div>

            <div class="field field-full">
                <label for="instructions">Production instructions</label>
                <textarea id="instructions" name="instructions">{{ old('instructions', $order?->instructions) }}</textarea>
            </div>

            <div class="field field-full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes">{{ old('notes', $order?->notes) }}</textarea>
            </div>
        </div>
    </div>
</section>
