{{-- Fields of the "Add flasks" dialog (partials.quick-dialog): a batch of identical flasks. $sizes. --}}
@if ($part === 'required')
<div class="field"><label for="qd-fl-size">Size</label><select id="qd-fl-size" name="flask_size_id" required>@foreach ($sizes->where('status', 'ACTIVE') as $s)<option value="{{ $s->id }}" @selected($old('flask_size_id', request('size')) === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
<div class="field"><label for="qd-fl-pos">Position</label><select id="qd-fl-pos" name="position" required>@foreach (\App\Models\Flask::POSITIONS as $k => $l)<option value="{{ $k }}" @selected($old('position', 'DRAG') === $k)>{{ $l }}</option>@endforeach</select></div>
<div class="field"><label for="qd-fl-qty">How many</label><input id="qd-fl-qty" type="number" name="quantity" min="1" max="200" required value="{{ $old('quantity', 1) }}"></div>
<div class="field field-full"><label for="qd-fl-marks">Stamped marks</label><textarea id="qd-fl-marks" name="marks" rows="2" placeholder="One per flask, separated by spaces or new lines. Leave blank to generate them." style="font-family:ui-monospace,Menlo,monospace">{{ $old('marks') }}</textarea></div>
@else
<div class="field"><label for="qd-fl-con">Construction</label><select id="qd-fl-con" name="construction"><option value="">—</option>@foreach (\App\Models\Flask::CONSTRUCTIONS as $k => $l)<option value="{{ $k }}" @selected($old('construction') === $k)>{{ $l }}</option>@endforeach</select></div>
<div class="field"><label for="qd-fl-mat">Material</label><input id="qd-fl-mat" name="material" maxlength="50" placeholder="e.g. Steel" value="{{ $old('material') }}"></div>
<div class="field"><label for="qd-fl-wt">Empty weight (kg)</label><input id="qd-fl-wt" type="number" name="weight_kg" min="0.1" step="any" value="{{ $old('weight_kg') }}"></div>
<div class="field"><label for="qd-fl-loc">Location</label><input id="qd-fl-loc" name="location" maxlength="100" placeholder="e.g. Bay 2, rack A" value="{{ $old('location') }}"></div>
<p class="muted small field-full" style="margin:0">Each flask gets the next code (FLK-…). Fixed cope–drag pairs are set on a flask's own page.</p>
@endif
