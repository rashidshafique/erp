@props(['heat', 'after' => false])
@php
    $light = $heat?->light();
    $colour = ['red' => '#d93025', 'orange' => '#f29900', 'green' => '#1e8e3e'][$light] ?? null;
    $rgb = ['red' => '217,48,37', 'orange' => '242,153,0', 'green' => '30,142,62'][$light] ?? null;
@endphp
@if ($colour)<span class="heat-light heat-light-{{ $light }}" title="{{ $heat->lightLabel() }}" aria-label="{{ $heat->lightLabel() }}" style="--hl:{{ $colour }};--hl-rgb:{{ $rgb }};{{ $after ? 'margin-left:6px' : 'margin-right:6px' }}"></span>@endif
