{{-- A row of tabs that filter a list by status, each with its count as a badge. The current one is underlined.
     $options: value => label ('' for All); $counts: value => count (missing or 0 shows no count); $current; $param (default 'status').
     Other query values (search, supplier) are kept; the page goes back to 1. --}}
@props(['options', 'counts' => [], 'current' => '', 'param' => 'status'])
<nav {{ $attributes->class(['status-tabs']) }} aria-label="Filter by status">
    @foreach ($options as $value => $label)
        @php $count = $counts[$value] ?? 0; @endphp
        <a href="{{ request()->fullUrlWithQuery([$param => $value === '' ? null : $value, 'page' => null]) }}" @class(['active' => (string) $current === (string) $value]) @if ((string) $current === (string) $value) aria-current="page" @endif>{{ $label }}@if ($count > 0)<span class="n">{{ \App\Support\Formats::number($count) }}</span>@endif</a>
    @endforeach
</nav>
@once
<style>
    .status-tabs { display: flex; gap: 22px; height: 38px; border-bottom: 1px solid #e6e7e9; overflow-x: auto; white-space: nowrap; font-size: 13px; }
    .status-tabs a { display: inline-flex; align-items: center; gap: 7px; color: #55585d; text-decoration: none !important; }
    .status-tabs a:hover { color: #1c1d1f; }
    .status-tabs a.active { font-weight: 600; color: #1c1d1f; box-shadow: inset 0 -2px 0 var(--accent); }
    .status-tabs .n { display: inline-flex; align-items: center; justify-content: center; min-width: 21px; height: 19px; padding: 0 6px; box-sizing: border-box; border-radius: 10px; background: #eceef1; color: #55585d; font-size: 11.5px; font-weight: 600; font-variant-numeric: tabular-nums; line-height: 1; }
    .status-tabs a.active .n { background: color-mix(in srgb, var(--accent) 14%, #fff); color: var(--accent); }
    .list-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 16px; }
    .list-toolbar form { display: flex; flex: 1 1 260px; max-width: 520px; }
    .list-toolbar form input[type=search] { flex: 1; min-width: 0; border-radius: 8px 0 0 8px; }
    .list-toolbar form .btn { border-radius: 0 8px 8px 0; }
</style>
@endonce
