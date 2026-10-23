{{-- A flat illustration of how a pattern revision is moulded: flasks, parting line, cavities, sprue and runners, drawn for its
     arrangement (two-part, stack, vertical flaskless, tree), with the castings per mould under it. Colours come from the
     accent. $revision (PatternRevision or null), $size ('lg' or 'sm'). --}}
@once
<style>
    .mould-fig { display: inline-flex; flex-direction: column; align-items: center; gap: 4px; }
    .mould-fig svg { display: block; height: auto; }
    .mould-fig .md-bg { fill: var(--accent); fill-opacity: .06; }
    .mould-fig .md-flask { fill: var(--accent); fill-opacity: .1; stroke: var(--accent); stroke-opacity: .55; stroke-width: 1.5; }
    .mould-fig .md-flask-alt { fill-opacity: .2; }
    .mould-fig .md-core { stroke-dasharray: 4 3; }
    .mould-fig .md-parting { stroke: var(--accent); stroke-opacity: .7; stroke-width: 1.2; stroke-dasharray: 3 3; }
    .mould-fig .md-cavity { fill: var(--surface); stroke: var(--accent); stroke-width: 1.8; }
    .mould-fig .md-metal { fill: var(--accent); stroke: none; }
    .mould-fig .md-runner { stroke: var(--accent); stroke-width: 3; stroke-linecap: round; fill: none; }
    .mould-fig .md-label { fill: var(--muted); font: 600 9px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; letter-spacing: .04em; text-transform: uppercase; }
    .mould-fig figcaption { font-size: 12px; color: var(--muted); text-align: center; line-height: 1.35; }
    .mould-fig figcaption strong { color: var(--text); font-size: 13px; }
</style>
@endonce
@php
    $size ??= 'lg';
    $arrangement = $revision?->mould_arrangement ?? 'COPE_DRAG';
    $levels = $revision?->castingLevels() ?? 1;
    $sections = max(1, (int) ($revision?->layers_per_mould ?? 2));
    $cavities = $revision ? max($revision->cavityMap->count(), (int) $revision->cavities_per_mould) : 0;
    $linked = $revision ? $revision->cavityMap->where('is_closed', false)->count() : 0;
    $allClosed = $revision && $revision->cavityMap->isNotEmpty() && $linked === 0;
    // Only cavities linked to a product make castings; before any are linked, the layout's count stands in.
    $castings = $levels * ($linked ?: ($allClosed ? 0 : $cavities));
    $kind = match ($arrangement) {
        'STACK', 'CORE_PACKAGE' => 'stack',
        'VERTICAL' => 'vertical',
        'CLUSTER' => 'tree',
        default => 'twopart',
    };
    $label = $revision ? $revision->arrangementLabel() : 'No revision yet';
    // $n positions spread evenly between $from and $to.
    $spread = function (int $n, float $from, float $to): array {
        if ($n <= 0) {
            return [];
        }
        if ($n === 1) {
            return [($from + $to) / 2];
        }
        $step = ($to - $from) / ($n - 1);

        return array_map(fn ($i) => $from + $i * $step, range(0, $n - 1));
    };
    $shown = min(max($cavities, 1), 6);
@endphp
<figure class="mould-fig" style="margin:0">
    <svg viewBox="0 0 250 170" width="{{ $size === 'sm' ? 150 : 250 }}" role="img" aria-label="{{ $label }}: {{ $levels }} {{ $levels === 1 ? 'level' : 'levels' }} of {{ $cavities }} {{ $cavities === 1 ? 'cavity' : 'cavities' }}, {{ $castings }} castings per mould">
        <rect class="md-bg" x="4" y="4" width="242" height="162" rx="22"/>
        @if (! $revision)
            <rect class="md-flask md-core" x="64" y="44" width="122" height="82" rx="10"/>
            <text class="md-label" x="125" y="89" text-anchor="middle">no layout</text>
        @elseif ($kind === 'twopart')
            <rect class="md-flask" x="44" y="34" width="160" height="50" rx="9"/>
            <rect class="md-flask md-flask-alt" x="44" y="84" width="160" height="50" rx="9"/>
            <line class="md-parting" x1="36" y1="84" x2="212" y2="84"/>
            @php $xs = $spread($shown, 92, 188); $w = min(22, 90 / max(1, $shown)); @endphp
            <line class="md-runner" x1="62" y1="84" x2="{{ end($xs) ?: 62 }}" y2="84"/>
            <path class="md-metal" d="M52 14 h20 l-5 12 h-10 z"/><rect class="md-metal" x="58" y="24" width="8" height="60" rx="3"/>
            @foreach ($xs as $x)<rect class="md-cavity" x="{{ $x - $w / 2 }}" y="76" width="{{ $w }}" height="16" rx="7"/>@endforeach
            <text class="md-label" x="212" y="62">cope</text><text class="md-label" x="212" y="112">drag</text>
            @if ($cavities > $shown)<text class="md-label" x="124" y="152" text-anchor="middle">+{{ $cavities - $shown }} more cavities</text>@endif
        @elseif ($kind === 'stack')
            @php
                $drawn = min($sections, 7); $top = 26; $height = 122; $h = $height / max(1, $drawn);
                $per = min(max($cavities, 1), 4); $xs = array_merge($spread(intdiv($per + 1, 2), 64, 104), $spread(intdiv($per, 2), 146, 186));
                $w = min(20, 60 / max(1, $per));
            @endphp
            @for ($i = 0; $i < $drawn; $i++)
                <rect class="md-flask {{ $i % 2 ? 'md-flask-alt' : '' }} {{ $arrangement === 'CORE_PACKAGE' ? 'md-core' : '' }}" x="50" y="{{ $top + $i * $h }}" width="150" height="{{ $h - 1.5 }}" rx="6"/>
            @endfor
            <path class="md-metal" d="M115 8 h20 l-5 12 h-10 z"/><rect class="md-metal" x="121" y="18" width="8" height="{{ $top + ($drawn - 1) * $h - 18 + 4 }}" rx="3"/>
            @for ($j = 1; $j < $drawn && $j <= $levels; $j++)
                @php $y = $top + $j * $h - 0.75; @endphp
                <line class="md-parting" x1="44" y1="{{ $y }}" x2="206" y2="{{ $y }}"/>
                <line class="md-runner" x1="{{ $xs[0] ?? 64 }}" y1="{{ $y }}" x2="{{ end($xs) ?: 186 }}" y2="{{ $y }}"/>
                @foreach ($xs as $x)<rect class="md-cavity" x="{{ $x - $w / 2 }}" y="{{ $y - 5 }}" width="{{ $w }}" height="10" rx="5"/>@endforeach
            @endfor
            <text class="md-label" x="212" y="{{ $top + 10 }}">top</text><text class="md-label" x="212" y="{{ $top + $height - 4 }}">base</text>
            @if ($sections > $drawn)<text class="md-label" x="125" y="162" text-anchor="middle">{{ $sections }} sections · {{ $sections - $drawn }} not drawn</text>@endif
        @elseif ($kind === 'vertical')
            @php $per = min(max($cavities, 1), 3); $ys = $spread($per, 64, 116); @endphp
            @for ($b = 0; $b < 4; $b++)
                <rect class="md-flask {{ $b % 2 ? 'md-flask-alt' : '' }}" x="{{ 26 + $b * 50 }}" y="40" width="48" height="96" rx="7"/>
            @endfor
            @for ($j = 1; $j < 4; $j++)
                @php $x = 25 + $j * 50; @endphp
                <line class="md-parting" x1="{{ $x }}" y1="34" x2="{{ $x }}" y2="142"/>
                <path class="md-metal" d="M{{ $x - 8 }} 22 h16 l-4 10 h-8 z"/><rect class="md-metal" x="{{ $x - 2.5 }}" y="30" width="5" height="{{ end($ys) - 30 }}" rx="2"/>
                @foreach ($ys as $y)<rect class="md-cavity" x="{{ $x - 6 }}" y="{{ $y - 8 }}" width="12" height="16" rx="5"/>@endforeach
            @endfor
            <text class="md-label" x="125" y="158" text-anchor="middle">mould string →</text>
        @else
            @php $per = min(max($cavities, 1), 8); @endphp
            <path class="md-metal" d="M115 12 h20 l-5 12 h-10 z"/><rect class="md-metal" x="121" y="22" width="8" height="122" rx="3"/>
            @for ($k = 0; $k < $per; $k++)
                @php $y = 40 + intdiv($k, 2) * 26; $left = $k % 2 === 0; $x2 = $left ? 74 : 176; @endphp
                <line class="md-runner" x1="125" y1="{{ $y }}" x2="{{ $x2 }}" y2="{{ $y + 8 }}"/>
                <rect class="md-cavity" x="{{ $x2 - 12 }}" y="{{ $y }}" width="24" height="16" rx="7"/>
            @endfor
        @endif
    </svg>
    <figcaption>
        @if ($revision)
            <strong>{{ $castings }} {{ $castings === 1 ? 'casting' : 'castings' }} per mould</strong><br>
            {{ $levels }} {{ $levels === 1 ? 'level' : 'levels' }} × {{ $cavities }} {{ $cavities === 1 ? 'cavity' : 'cavities' }}@if ($linked && $linked < $cavities) ({{ $linked }} linked)@endif @if ($size === 'lg') · {{ \Illuminate\Support\Str::before($label, ' (') }}@endif
        @else
            No revision recorded yet
        @endif
    </figcaption>
</figure>
