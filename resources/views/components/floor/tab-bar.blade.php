{{--
    design: C1. The tablet tab bar: Home, the role's tabs in flow order, Scan last. 64 px, then a 28 px band kept clear for
    Android buttons. Tabs the user may not open are left out by the caller, never greyed; only Scan greys, when the camera is
    blocked. The page around it takes class fl-has-tabs so nothing ends under the bar.
--}}
@props([
    // list of ['label' => string, 'href' => string, 'active' => bool, 'count' => ?int, 'tone' => null|'warn'|'bad']
    'tabs' => [],
    'scan' => null,          // href of Scan; null leaves it out
    'scanActive' => false,
    'scanBlocked' => false,
])
<div {{ $attributes->class(['fl-tabs']) }}>
    <nav aria-label="Tablet sections">
        @foreach ($tabs as $tab)
            @php
                $count = (int) ($tab['count'] ?? 0);
                $tone = $tab['tone'] ?? null;
                $badge = match (true) {
                    $tone === 'bad' => ['is-bad', '!'],
                    $tone === 'warn' => ['is-warn', '! '.max(1, $count)],
                    $count > 0 => ['', (string) $count],
                    default => null,
                };
            @endphp
            <a href="{{ $tab['href'] }}" @class(['fl-tab', 'is-active' => $tab['active'] ?? false]) @if ($tab['active'] ?? false) aria-current="page" @endif>
                {{ $tab['label'] }}
                @if ($badge)<span class="fl-badge {{ $badge[0] }}">{{ $badge[1] }}</span>@endif
            </a>
        @endforeach
        @if ($scan)
            <a href="{{ $scan }}" @class(['fl-tab', 'is-active' => $scanActive, 'is-disabled' => $scanBlocked]) @if ($scanBlocked) aria-disabled="true" @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"/></svg>
                Scan
            </a>
        @endif
    </nav>
</div>
