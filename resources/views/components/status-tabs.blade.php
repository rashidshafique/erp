{{-- A row of joined buttons that filter a list by status, each with its count. The current one is filled.
     $options: value => label ('' for All); $counts: value => count (missing or 0 shows no count); $current; $param (default 'status').
     Other query values (search, supplier) are kept; the page goes back to 1. --}}
@props(['options', 'counts' => [], 'current' => '', 'param' => 'status'])
<nav {{ $attributes->class(['status-tabs']) }} aria-label="Filter by status">
    @foreach ($options as $value => $label)
        @php $count = $counts[$value] ?? 0; @endphp
        <a href="{{ request()->fullUrlWithQuery([$param => $value === '' ? null : $value, 'page' => null]) }}" @class(['active' => (string) $current === (string) $value]) @if ((string) $current === (string) $value) aria-current="page" @endif>{{ $label }}@if ($count > 0) <span>({{ $count }})</span>@endif</a>
    @endforeach
</nav>
@once
<style>
    .status-tabs { display: inline-flex; flex-wrap: wrap; border: 1px solid var(--accent); border-radius: 8px; overflow: hidden; background: var(--panel, #fff); }
    .status-tabs a { padding: 8px 13px; color: var(--accent); text-decoration: none; border-left: 1px solid color-mix(in srgb, var(--accent) 35%, transparent); white-space: nowrap; font-size: 14px; }
    .status-tabs a:first-child { border-left: 0; }
    .status-tabs a:hover { background: color-mix(in srgb, var(--accent) 8%, transparent); }
    .status-tabs a.active { background: var(--text, #1f2937); color: #fff; border-color: var(--text, #1f2937); }
    .status-tabs a span { opacity: .75; }
    .list-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 16px; }
    .list-toolbar form { display: flex; flex: 1 1 260px; max-width: 520px; }
    .list-toolbar form input[type=search] { flex: 1; min-width: 0; border-radius: 8px 0 0 8px; }
    .list-toolbar form .btn { border-radius: 0 8px 8px 0; }
</style>
@endonce
