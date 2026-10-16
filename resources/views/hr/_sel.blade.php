{{-- A labelled dropdown. $name, $label, $items (id => text), $sel, optional $blank, $required --}}
<div class="field" @if (! empty($keep)) data-keep @endif><label for="f_{{ $name }}">{{ $label }}{{ ! empty($required) ? ' *' : '' }}</label>
<select id="f_{{ $name }}" name="{{ $name }}" @if (! empty($required)) required @endif>
    @if (($blank ?? '') !== false)<option value="">{{ $blank ?? '—' }}</option>@endif
    @foreach ($items as $k => $t)<option value="{{ $k }}" @selected((string) old($name, $sel ?? '') === (string) $k)>{{ $t }}</option>@endforeach
</select>@error($name)<div class="error-text">{{ $message }}</div>@enderror</div>
