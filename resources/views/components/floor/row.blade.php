{{--
    design: C3. One row of a due-now home: title, one meta line, the age, and one button. There is never a second button and no
    done button; the row goes when the work is done elsewhere. With no action it reads "Not ready" and opens read-only.
--}}
@props([
    'title',
    'meta' => null,
    'age' => null,
    'tone' => null,          // null, 'warn' (past the stage's normal time) or 'bad'
    'href' => null,          // where the row and its button go
    'action' => null,        // the button label; none = Not ready
    'disabled' => false,     // only when the meta line says why
    'link' => false,         // phone: the button is a link
])
@php
    $age = $age !== null && $tone ? '! '.$age : $age;
    $buttonClass = match (true) {
        $link => 'fl-btn is-link',
        $tone === 'bad' => 'fl-btn is-bad-outline',
        default => 'fl-btn is-primary',
    };
@endphp
<{{ $href && ! $disabled ? 'a' : 'div' }} @if ($href && ! $disabled) href="{{ $href }}" @endif
    {{ $attributes->class(['fl-row', 'is-'.$tone => $tone, 'is-disabled' => $disabled]) }}>
    <span style="min-width:0">
        <span class="fl-row-title">{{ $title }}</span>
        @if ($meta !== null && $meta !== '')<span class="fl-row-meta">{{ $meta }}</span>@endif
    </span>
    <span class="fl-row-age">{{ $age }}</span>
    @if ($action)
        <span class="{{ $buttonClass }}{{ $disabled ? ' is-disabled' : '' }}" @if ($disabled) aria-disabled="true" @endif>{{ $action }}</span>
    @else
        <span class="fl-not-ready">○ Not ready</span>
    @endif
</{{ $href && ! $disabled ? 'a' : 'div' }}>
