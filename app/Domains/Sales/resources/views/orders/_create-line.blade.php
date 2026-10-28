{{-- One line of the sales order form: $i (index, or __I__ in the template), $value (revision id or product:<id>, empty on a new
     row), $p (name, sub, img, price, uom, kg), $catalogue (every product that can be ordered, keyed by value), $line (input values),
     $record (the saved line when editing, else null), $currency, $prices (show the price). The product is picked in the row. The caret opens the line's
     own delivery date, repeat settings and, once the line is saved, its staggered deliveries. A saved line with production orders keeps its product and cannot be removed. --}}
@php
    $repeats = ! empty($line['repeat_every']);
    $staggered = ! $repeats && ! empty($line['staggered']);
    $deliveries = array_values((array) ($line['deliveries'] ?? []));
    $hasDate = ! empty($line['requested_delivery_date']) || $repeats || $staggered;
    $locked = $record && $record->productionOrders->where('status', '!=', 'CANCELLED')->isNotEmpty();
    // The line's own unit when saved or posted back, else the product's base unit: kg sells by weight, anything else by the piece.
    $unit = $line['uom'] ?? (strtolower((string) $p['uom']) === 'kg' ? 'kg' : 'piece');
    // What the preview adds the new deliveries to: deliveries before today on a repeating line, else what was already shipped.
    $kept = ! $record ? 0 : ($record->repeats() ? (float) $record->deliveries->where('due_date', '<', today())->sum('quantity')
        : (float) (\App\Domains\Sales\Models\SalesOrderLine::shippedByLine([$record->id])[$record->id] ?? 0));
@endphp
<div class="so-line {{ $hasDate ? 'is-open' : '' }}" data-line data-was-repeating="{{ $record?->repeats() ? 1 : 0 }}" data-kept="{{ $kept }}" data-ordered="{{ $record ? (float) $record->ordered_qty : 0 }}" data-each-kg="{{ $p['kg'] ?? '' }}">
    <div class="so-grid">
        <button type="button" class="so-caret" data-toggle aria-expanded="{{ $hasDate ? 'true' : 'false' }}" aria-label="Line details">&#9656;</button>
        @if ($p['img'])<img class="so-thumb" src="{{ $p['img'] }}" alt="">@else<span class="so-thumb" aria-hidden="true">&#9638;</span>@endif
        <div class="so-prod">
            @if ($locked)
                <strong>{{ $p['name'] }}</strong><small>{{ $p['sub'] }}</small><small class="muted" style="display:block">Has production orders: cannot go below what is scheduled or shipped</small>
                <input type="hidden" name="lines[{{ $i }}][product_revision_id]" value="{{ $value }}">
            @else
                <select name="lines[{{ $i }}][product_revision_id]" data-search-select="photos" data-product aria-label="Product">
                    <option value="">Select product</option>
                    @if ($value !== '' && ! isset($catalogue[$value]))<option value="{{ $value }}" data-sub="{{ $p['sub'] }}" data-uom="{{ $p['uom'] }}" data-kg="{{ $p['kg'] ?? '' }}" selected>{{ $p['name'] }}</option>@endif
                    @foreach ($catalogue as $v => $c)<option value="{{ $v }}" data-sub="{{ $c['sub'] }}" @if ($c['img']) data-img="{{ $c['img'] }}" @endif @if ($prices && $c['price'] !== null) data-price="{{ $c['price'] }}" @endif data-uom="{{ $c['uom'] }}" data-kg="{{ $c['kg'] }}" @selected((string) $v === (string) $value)>{{ $c['name'] }}</option>@endforeach
                </select>
                <small data-sub>{{ $p['sub'] }}</small>
            @endif
        </div>
        @if ($record)<input type="hidden" name="lines[{{ $i }}][id]" value="{{ $record->id }}">@endif
        {{-- Production always makes pieces; a line sold by kg is priced, shipped and invoiced on the weighed net kg. --}}
        <span class="so-uom">@if ($locked){{ \App\Domains\Sales\Models\SalesOrderLine::UNITS[$unit] }}<input type="hidden" name="lines[{{ $i }}][uom]" value="{{ $unit }}" data-unit>@else<select name="lines[{{ $i }}][uom]" aria-label="Sold by" data-unit>@foreach (\App\Domains\Sales\Models\SalesOrderLine::UNITS as $key => $label)<option value="{{ $key }}" @selected($unit === $key)>{{ $label }}</option>@endforeach</select>@endif</span>
        <div class="so-qty"><input type="number" name="lines[{{ $i }}][ordered_qty]" step="any" min="0" @required($value !== '') value="{{ $line['ordered_qty'] ?? '' }}" placeholder="{{ $repeats ? 'Each time' : 'Qty' }}" aria-label="Quantity" data-qty><small data-pcs-hint @if ($unit !== 'kg') hidden @endif></small></div>
        @if ($prices)
        <label class="so-money"><input type="number" name="lines[{{ $i }}][unit_price]" step="any" min="0" value="{{ array_key_exists('unit_price', $line) ? $line['unit_price'] : $p['price'] }}" aria-label="Unit price" data-price><span>{{ $currency ?: 'price' }}</span></label>
        @if ($discounts)<label class="so-money so-pct"><input type="number" name="lines[{{ $i }}][discount_percent]" step="any" min="0" max="100" value="{{ $line['discount_percent'] ?? '' }}" placeholder="0" aria-label="Discount percent" data-disc><span>%</span></label>@else<input type="hidden" name="lines[{{ $i }}][discount_percent]" value="{{ $line['discount_percent'] ?? '' }}" data-disc>@endif
        <span class="so-total" data-total>—</span>
        @else
        {{-- Prices are hidden: a saved price is sent back unchanged so editing never clears it. --}}
        <input type="hidden" name="lines[{{ $i }}][unit_price]" value="{{ $line['unit_price'] ?? '' }}">
        <input type="hidden" name="lines[{{ $i }}][discount_percent]" value="{{ $line['discount_percent'] ?? '' }}">
        @endif
        @if ($locked)<span></span>@else<button type="button" class="so-remove" data-remove aria-label="Remove line">&times;</button>@endif
    </div>
    <div class="so-extra" data-extra @unless ($hasDate) hidden @endunless>
        <div class="field"><label>Line delivery date</label><input type="date" name="lines[{{ $i }}][requested_delivery_date]" value="{{ $line['requested_delivery_date'] ?? '' }}"></div>
        <div class="field"><label>Repeats</label><select name="lines[{{ $i }}][repeat_every]" data-repeat>
            <option value="">No, one order</option>
            @foreach (\App\Domains\Sales\Models\SalesOrderLine::REPEATS as $key => $label)<option value="{{ $key }}" @selected(($line['repeat_every'] ?? '') === $key)>{{ $label }}</option>@endforeach
        </select></div>
        @if ($record)<label class="so-check" data-stagger-field @if ($repeats) hidden @endif><input type="hidden" name="lines[{{ $i }}][staggered]" value="0"><input type="checkbox" name="lines[{{ $i }}][staggered]" value="1" data-stagger @checked($staggered)> Staggered deliveries</label>@endif
        <div class="field" data-repeat-field @unless ($repeats) hidden @endunless><label>First delivery</label><input type="date" name="lines[{{ $i }}][repeat_from]" value="{{ $line['repeat_from'] ?? '' }}"></div>
        <div class="field" data-repeat-field @unless ($repeats) hidden @endunless><label>Until (optional)</label><input type="date" name="lines[{{ $i }}][repeat_until]" value="{{ $line['repeat_until'] ?? '' }}"></div>
        <span class="muted small" data-repeat-note @if ($repeats) hidden @endif>Leave empty to use the order's requested delivery. {{ $record ? 'Tick staggered deliveries to split the quantity over several dates.' : 'Staggered deliveries can be set once the order is saved.' }}</span>
        <span class="muted small" data-repeat-field @unless ($repeats) hidden @endunless>No PO each time: the quantity box is the quantity of each delivery, and the next deliveries are added on their own. The line's total is its deliveries, so a one-off quantity is replaced; for a one-off as well, add the product again as a second line. Stopping keeps the deliveries already scheduled.</span>
    </div>
    @if ($record)
    <div class="so-deliveries" data-deliveries @unless ($staggered) hidden @endunless>
        <div data-delivery-rows>
            @foreach ($deliveries as $d => $row)
                @include('sales::orders._line-delivery', ['i' => $i, 'd' => $d, 'row' => $row, 'off' => ! $staggered])
            @endforeach
        </div>
        <div class="so-delivery-foot"><button type="button" class="btn btn-small" data-add-delivery>+ Add delivery</button><span class="muted small" data-unscheduled aria-live="polite"></span></div>
        <template data-delivery-template>@include('sales::orders._line-delivery', ['i' => $i, 'd' => '__D__', 'row' => [], 'off' => false])</template>
    </div>
    @endif
    <div class="so-preview" data-repeat-preview @unless ($repeats) hidden @endunless aria-live="polite"></div>
</div>
