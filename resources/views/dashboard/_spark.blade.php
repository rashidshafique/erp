@php
    $vals = array_values($vals ?? []);
    $w = $width ?? 84; $h = $height ?? 26; $n = count($vals); $max = max(array_merge($vals, [0.0001])); $min = min(array_merge($vals, [$max]));
    $span = ($max - $min) ?: 1; $pts = [];
    foreach ($vals as $i => $v) { $pts[] = round($n > 1 ? $i * $w / ($n - 1) : 0, 1).','.round($h - 3 - ($v - $min) / $span * ($h - 6), 1); }
@endphp
@if ($n > 1)<svg width="{{ $w }}" height="{{ $h }}" viewBox="0 0 {{ $w }} {{ $h }}" aria-hidden="true"><polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="{{ $color ?? 'var(--accent)' }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>@endif
