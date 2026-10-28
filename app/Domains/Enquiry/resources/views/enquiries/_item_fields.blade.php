@php $v = fn ($k, $d = null) => old($k, $item?->{$k} ?? $d); @endphp
<div class="form-grid" data-progressive="3">
    <div class="field field-full"><label>Description</label><input name="description" value="{{ $v('description') }}" required placeholder="What the part is"></div>
    <div class="field"><label>Existing product</label>@include('partials.search-select')@php($productPhotos = \App\Models\Attachment::coverUrlsFor('product', $products->pluck('id')->all()))<select name="product_id" data-search-select="photos"><option value="">— new part —</option>@foreach ($products as $p)<option value="{{ $p->id }}" data-sub="{{ $p->code }}" @isset($productPhotos[$p->id]) data-img="{{ $productPhotos[$p->id] }}" @endisset @selected($v('product_id') === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
    <div class="field"><label>Iron family</label><select name="family"><option value="">— decide later —</option><option value="GREY" @selected($v('family') === 'GREY')>Grey iron</option><option value="DUCTILE" @selected($v('family') === 'DUCTILE')>Ductile iron</option></select></div>
    <div class="field"><label>Drawing number</label><input name="drawing_ref" value="{{ $v('drawing_ref') }}"></div>
    <div class="field"><label>Drawing revision</label><input name="drawing_rev" value="{{ $v('drawing_rev') }}"></div>
    <div class="field"><label>Material asked for</label><input name="material_text" value="{{ $v('material_text') }}" placeholder="e.g. EN-GJS-500-7"></div>
    <div class="field"><label>Estimated weight, kg</label><input type="number" step="any" min="0" name="weight_kg" value="{{ $v('weight_kg') }}"><div class="help">Used when there is no analysis. An analysis overrides it.</div></div>
    <div class="field"><label>Yearly quantity</label><input type="number" min="0" name="annual_qty" value="{{ $v('annual_qty') }}"></div>
    <div class="field"><label>First batch</label><input type="number" min="0" name="first_batch_qty" value="{{ $v('first_batch_qty') }}"><div class="help">The quantity the quotation prices.</div></div>
    <div class="field"><label>Target price</label><input type="number" step="any" min="0" name="target_price" value="{{ $v('target_price') }}"></div>
    <div class="field"><label>Cores</label><input type="number" min="0" name="cores_count" value="{{ $v('cores_count') }}"><div class="help">Used when there is no analysis.</div></div>
    <div class="field"><label>Machining cost each</label><input type="number" step="any" min="0" name="machining_cost" value="{{ $v('machining_cost', 0) }}"></div>
    <div class="field"><label>Other cost each</label><input type="number" step="any" min="0" name="other_cost" value="{{ $v('other_cost', 0) }}"><div class="help">Coating, painting, outside processes.</div></div>
    <div class="field"><label>Tooling cost</label><input type="number" step="any" min="0" name="tooling_cost" value="{{ $v('tooling_cost', 0) }}"><div class="help">Pattern and core boxes we would make. Zero if the customer supplies them.</div></div>
    <div class="field"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="heat_treatment" value="1" @checked($v('heat_treatment'))> Heat treated</label><label style="display:flex;gap:8px;align-items:center;margin-top:6px"><input type="checkbox" name="ndt" value="1" @checked($v('ndt'))> NDT required</label></div>
    <div class="field"><label>Process</label><select name="process"><option value="">— decide later —</option>@foreach (\App\Domains\Enquiry\Models\EnquiryItem::PROCESSES as $k => $l)<option value="{{ $k }}" @selected($v('process') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label>Supply condition</label><select name="supply_condition"><option value="">— not stated —</option>@foreach (\App\Domains\Enquiry\Models\EnquiryItem::SUPPLY_CONDITIONS as $k => $l)<option value="{{ $k }}" @selected($v('supply_condition') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label>Batch / call-off</label><input name="call_off" maxlength="60" value="{{ $v('call_off') }}" placeholder="e.g. 8,000 / month"></div>
    <div class="field"><label>Start of production</label><input type="date" name="sop_on" value="{{ old('sop_on', $item?->sop_on?->toDateString()) }}"></div>
    <div class="field"><label>Programme life, years</label><input type="number" min="1" max="50" name="programme_years" value="{{ $v('programme_years') }}"></div>
    @php($q = $item?->quality ?? [])@php($tests = old('quality', $q['tests'] ?? []))
    <div class="field field-full"><label>Quality requirements</label>
        <div style="display:flex;flex-wrap:wrap;gap:6px 16px">@foreach (\App\Domains\Enquiry\Models\EnquiryItem::QUALITY as $k => $l)<label style="display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="quality[]" value="{{ $k }}" @checked(in_array($k, (array) $tests, true))> {{ $l }}</label>@endforeach</div>
        <div style="display:flex;gap:10px;margin-top:8px"><input type="number" min="1" max="5" name="ppap_level" value="{{ old('ppap_level', $q['ppap_level'] ?? '') }}" placeholder="PPAP level 1-5" style="max-width:150px" aria-label="PPAP level"><input name="quality_other" maxlength="200" value="{{ old('quality_other', $q['other'] ?? '') }}" placeholder="+ Customer-specific requirements" style="flex:1" aria-label="Customer-specific requirements"></div></div>
    <div class="field field-full"><label>Notes</label><textarea name="notes" rows="2">{{ $v('notes') }}</textarea></div>
</div>
