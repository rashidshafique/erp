{{-- design: C6. A status as a label with its symbol, so it reads without colour. Unknown or empty status shows a dash. --}}
@props(['status' => null])
@php
    /** @var array<string, array{0: string, 1: string, 2: string}> status => [symbol, words, class] */
    $chips = [
        'IN_PRODUCTION' => ['◐', 'In production', 'is-production'],
        'ON_HOLD' => ['!', 'On hold', 'is-hold'],
        'CLEAR' => ['✓', 'Clear', 'is-clear'],
        'READY' => ['✓', 'Ready', 'is-ready'],
        'PACKED' => ['▣', 'Packed', 'is-packed'],
        'DISPATCHED' => ['→', 'Dispatched', 'is-dispatched'],
        'OPEN' => ['○', 'Open', 'is-open'],
        'CLOSED' => ['●', 'Closed', 'is-closed'],
    ];
    $chip = $chips[strtoupper((string) $status)] ?? null;
@endphp
@if ($chip)
    <span {{ $attributes->class(['fl-chip', $chip[2]]) }}><span class="fl-chip-sym" aria-hidden="true">{{ $chip[0] }}</span>{{ $chip[1] }}</span>
@else
    <span {{ $attributes->class(['fl-row-age']) }}>—</span>
@endif
