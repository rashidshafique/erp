{{--
    design: C4. One check on a step: ✓ passed, ✗ failed with the one button that clears it, ! a rule the next step must respect,
    or still loading. Without permission the button is replaced by who can clear it. Sort failed, then warnings, then passed.
    A "clear" line is the empty state: nothing blocks.
--}}
@props([
    'state' => 'pass',       // pass, fail, warn, loading, clear
    'title',
    'detail' => null,
    'action' => null,        // label of the button that clears it (failed or warning lines only)
    'href' => null,
    'who' => null,           // shown instead of the button when the user may not clear it, e.g. "Quality manager only"
])
@php
    $mark = ['pass' => '✓', 'fail' => '✗', 'warn' => '!', 'loading' => '', 'clear' => '✓'][$state] ?? '';
    $words = ['pass' => 'Passed', 'fail' => 'Blocks', 'warn' => 'Warning', 'loading' => 'Checking', 'clear' => 'Clear'][$state] ?? '';
    $button = in_array($state, ['fail', 'warn', 'clear'], true) && ($action || $slot->isNotEmpty());
@endphp
<div {{ $attributes->class(['fl-blocker', 'is-'.$state]) }} @if ($state === 'loading') aria-busy="true" @endif>
    @unless ($state === 'clear')<span class="fl-blocker-mark" role="img" aria-label="{{ $words }}">{{ $mark }}</span>@endunless
    <span style="min-width:0">
        <span class="fl-blocker-title">@if ($state === 'clear')<x-floor.chip status="CLEAR" /> @endif{{ $title }}</span>
        @if ($detail)<span class="fl-blocker-detail">{{ $detail }}</span>@endif
    </span>
    @if ($who)
        <span class="fl-blocker-who">{{ $who }}</span>
    @elseif ($slot->isNotEmpty())
        {{ $slot }}
    @elseif ($button)
        <a href="{{ $href ?? '#' }}" class="fl-btn {{ $state === 'warn' ? '' : 'is-primary' }}">{{ $action }}</a>
    @endif
</div>
