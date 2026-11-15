{{-- Fields of the quick "New product" dialog (partials.quick-dialog) on the sales order lines. Drawing, grade, pattern and revision are added later on the product. --}}
@if ($part === 'required')
<div class="field"><label for="qd-prod-name">Name</label><input id="qd-prod-name" name="name" required maxlength="255" value="{{ $old('name') }}"></div>
<div class="field"><label for="qd-prod-code">Code</label><input id="qd-prod-code" name="code" maxlength="40" style="text-transform:uppercase" placeholder="Leave blank to generate: {{ app(\App\Services\Codes\CodeGenerator::class)->preview('product') }}" value="{{ $old('code') }}"></div>
@else
<div class="field"><label for="qd-prod-uom">Sold by</label><select id="qd-prod-uom" name="base_uom">@foreach (['piece' => 'Piece', 'kg' => 'Kilogram', 'set' => 'Set'] as $v => $l)<option value="{{ $v }}" @selected($old('base_uom', 'piece') === $v)>{{ $l }}</option>@endforeach</select></div>
<div class="field"><label for="qd-prod-kg">Casting weight (kg)</label><input id="qd-prod-kg" name="nominal_weight_kg" type="number" step="any" min="0" value="{{ $old('nominal_weight_kg') }}"></div>
@endif
