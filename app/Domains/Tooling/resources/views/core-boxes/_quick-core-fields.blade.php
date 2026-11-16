{{-- Fields of the quick "New core" dialog on the core box form (design 29f): just enough to pick it; the rest is on the core page. --}}
@if ($part === 'required')
<div class="field"><label for="qd-core-name">Name *</label><input id="qd-core-name" name="name" required maxlength="255" value="{{ $old('name') }}"></div>
<div class="field"><label for="qd-core-type">Type *</label><select id="qd-core-type" name="core_type" required>@foreach (\App\Models\CoreDefinition::TYPES as $k => $l)<option value="{{ $k }}" @selected($old('core_type', 'STANDARD') === $k)>{{ $l }}</option>@endforeach</select></div>
@else
<p class="muted small field-full" style="margin:0">Just enough to pick it. Finish the rest on the core page later.</p>
@endif
