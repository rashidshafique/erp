{{-- A labelled input. $name, $label, optional $type, $val, $required, $ph, $help, $keep --}}
<div class="field" @if (! empty($keep)) data-keep @endif><label for="f_{{ $name }}">{{ $label }}{{ ! empty($required) ? ' *' : '' }}</label>
<input id="f_{{ $name }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" value="{{ old($name, $val ?? '') }}" @if (! empty($required)) required @endif @if (! empty($ph)) placeholder="{{ $ph }}" @endif @if (($type ?? '') === 'number') step="any" @endif>
@if (! empty($help))<div class="help">{{ $help }}</div>@endif @error($name)<div class="error-text">{{ $message }}</div>@enderror</div>
