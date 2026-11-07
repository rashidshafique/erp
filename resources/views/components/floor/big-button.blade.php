{{--
    design: C8. The one next step on a furnace screen, at least 104 px tall: verb + the thing ("Charged #7"), and a second line
    with what will be recorded or why it is late. Warning and danger add the "!" box. With a reason it is disabled and says why;
    "empty" draws a dashed outline that is not a button. Inside a form it submits; with href it is a link.
--}}
@props([
    'label',
    'sub' => null,
    'tone' => null,          // null, 'warn' or 'bad'
    'href' => null,
    'disabled' => null,      // the reason it cannot be pressed, shown as the second line
    'empty' => false,
])
@php
    $classes = ['fl-big', 'is-'.$tone => $tone && ! $disabled && ! $empty, 'is-empty' => $empty, 'is-disabled' => (bool) $disabled];
@endphp
@if ($empty)
    <div {{ $attributes->class($classes) }}>
        <span class="fl-big-text"><span class="fl-big-label">{{ $label }}</span>@if ($sub)<span class="fl-big-sub">{{ $sub }}</span>@endif</span>
    </div>
@else
    @php($tag = $href && ! $disabled ? 'a' : 'button')
    <{{ $tag }} @if ($tag === 'a') href="{{ $href }}" @else type="{{ $attributes->get('type', 'submit') }}" @endif @disabled($disabled)
        {{ $attributes->except('type')->class($classes) }}>
        @if ($tone && ! $disabled)<span class="fl-big-mark" aria-hidden="true">!</span>@endif
        <span class="fl-big-text">
            <span class="fl-big-label">{{ $label }}</span>
            @if ($disabled || $sub)<span class="fl-big-sub">{{ $disabled ?: $sub }}</span>@endif
        </span>
        @unless ($disabled)<span class="fl-big-go" aria-hidden="true">›</span>@endunless
    </{{ $tag }}>
@endif
