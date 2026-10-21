@props(['heat'])
@php
    $light = $heat?->light();
    $colour = ['red' => '#d93025', 'orange' => '#f29900', 'green' => '#1e8e3e'][$light] ?? null;
@endphp
@if ($colour)<span title="{{ $heat->lightLabel() }}" aria-label="{{ $heat->lightLabel() }}" style="display:inline-block;width:11px;height:11px;border-radius:50%;background:{{ $colour }};box-shadow:0 0 0 3px {{ $colour }}33;margin-right:6px;vertical-align:middle"></span>@endif
