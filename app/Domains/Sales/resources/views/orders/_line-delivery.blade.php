{{-- One staggered delivery on a sales order form line: $i (line index), $d (delivery index, or __D__ in the template), $row (input
     values, with the saved delivery's id when editing), $off (inputs disabled while the line is not staggered). --}}
<div class="so-delivery" data-delivery-row>
    @if (! empty($row['id']))<input type="hidden" name="lines[{{ $i }}][deliveries][{{ $d }}][id]" value="{{ $row['id'] }}" @disabled($off)>@endif
    <input type="date" name="lines[{{ $i }}][deliveries][{{ $d }}][due_date]" value="{{ $row['due_date'] ?? '' }}" aria-label="Due" required @disabled($off)>
    <input type="number" name="lines[{{ $i }}][deliveries][{{ $d }}][quantity]" step="any" min="0" value="{{ $row['quantity'] ?? '' }}" placeholder="Qty" aria-label="Quantity" required data-delivery-qty @disabled($off)>
    <input name="lines[{{ $i }}][deliveries][{{ $d }}][notes]" maxlength="200" value="{{ $row['notes'] ?? '' }}" placeholder="Note" aria-label="Note" @disabled($off)>
    <button type="button" class="so-remove" data-remove-delivery aria-label="Remove delivery">&times;</button>
</div>
