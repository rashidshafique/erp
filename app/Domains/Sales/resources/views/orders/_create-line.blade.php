{{-- One line of the new sales order screen: $i (index, or __I__ in the template), $value (revision id or product:<id>), $p (name, sub,
     img, price, uom), $line (old input), $currency. The caret opens the line's own delivery date. --}}
@php($hasDate = ! empty($line['requested_delivery_date']))
<div class="so-line {{ $hasDate ? 'is-open' : '' }}" data-line>
    <div class="so-grid">
        <button type="button" class="so-caret" data-toggle aria-expanded="{{ $hasDate ? 'true' : 'false' }}" aria-label="Line details">&#9656;</button>
        @if ($p['img'])<img class="so-thumb" src="{{ $p['img'] }}" alt="">@else<span class="so-thumb" aria-hidden="true">&#9638;</span>@endif
        <div class="so-prod"><strong data-name>{{ $p['name'] }}</strong><small data-sub>{{ $p['sub'] }}</small></div>
        <input type="hidden" name="lines[{{ $i }}][product_revision_id]" value="{{ $value }}" data-value>
        <label class="so-money"><input type="number" name="lines[{{ $i }}][unit_price]" step="any" min="0" value="{{ $line['unit_price'] ?? $p['price'] }}" aria-label="Unit price" data-price><span>{{ $currency ?: 'price' }}</span></label>
        <span class="so-times" aria-hidden="true">&times;</span>
        <input class="so-qty" type="number" name="lines[{{ $i }}][ordered_qty]" step="any" min="0.001" required value="{{ $line['ordered_qty'] ?? '' }}" placeholder="Qty" aria-label="Quantity" data-qty>
        <span class="so-uom" data-uom>{{ $p['uom'] }}</span>
        <span class="so-total" data-total>—</span>
        <button type="button" class="so-remove" data-remove aria-label="Remove line">&times;</button>
    </div>
    <div class="so-extra" data-extra @unless ($hasDate) hidden @endunless>
        <div class="field"><label>Line delivery date</label><input type="date" name="lines[{{ $i }}][requested_delivery_date]" value="{{ $line['requested_delivery_date'] ?? '' }}"></div>
        <span class="muted small">Leave empty to use the order's requested delivery. Staggered deliveries are set on the order once it is saved.</span>
    </div>
</div>
