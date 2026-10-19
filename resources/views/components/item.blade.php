{{-- A product, pattern or order on two lines: the name large, the code (and revision) below in monospace.
     $name, $code, optional $revision (a PatternRevision's code or a product revision letter, shown after the code) and $href. --}}
@props(['name' => null, 'code' => null, 'revision' => null, 'href' => null])
<span {{ $attributes->class(['item']) }}>
    @if ($href)<a href="{{ $href }}" class="item-name">{{ $name ?: ($code ?: '—') }}</a>@else<span class="item-name">{{ $name ?: ($code ?: '—') }}</span>@endif
    @if ($name && $code || $revision)<span class="item-code code">{{ $code }}@if ($revision) · {{ $revision }}@endif</span>@endif
</span>
