<?php

use App\Models\Heat;
use App\Services\Production\ChargeSchedule;
use App\Services\Production\HeatEta;
use App\Services\Production\HeatSamples;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * What matters about a heat at a glance, above its tabs: the metal charged (worked out from the charges) and the latest test first,
 * then how long it has run and how long is left, and the charges in with the last one and the next. The clocks count in the browser;
 * the rest is refreshed every half minute and whenever a charge is changed.
 */
new class extends Component
{
    public string $heatId;

    public function mount(string $heatId): void
    {
        $this->heatId = $heatId;
    }

    #[On('charges-changed')]
    public function refresh(): void {}

    /** @return array<string, mixed> */
    public function with(): array
    {
        $heat = Heat::with(['furnace', 'chargeBatch.items', 'materialConsumptions.material'])->findOrFail($this->heatId);
        $open = ! in_array($heat->status, ['CLOSED', 'CANCELLED'], true);
        $run = $heat->furnaceRun();
        $entries = $heat->materialConsumptions->whereNotNull('charge_round');
        $lastRound = $entries->isEmpty() ? null : $entries->groupBy('charge_round')->map(fn ($e) => $e->max('consumed_at'))->sortKeys()->keys()->last();
        $schedule = $heat->chargeBatch ? app(ChargeSchedule::class)->for($heat) : null;
        $tests = app(HeatSamples::class);

        return [
            'heat' => $heat,
            'open' => $open,
            'run' => $run,
            'finish' => $run['running'] ? app(HeatEta::class)->for($heat) : null,
            'metal' => $heat->chargedMetalKg(),
            'rate' => app(HeatEta::class)->rate($heat),
            'planned' => $heat->chargeRounds(),
            'done' => $entries->where('charge_round', '>', 0)->pluck('charge_round')->unique()->count(),
            'bedIn' => $entries->where('charge_round', 0)->isNotEmpty(),
            'lastRound' => $lastRound,
            'lastAt' => $lastRound === null ? null : $entries->where('charge_round', $lastRound)->max('consumed_at'),
            'next' => $open ? ($schedule['next'] ?? null) : null,
            'latest' => $tests->timeline($heat)->last(),
            'due' => $open ? $tests->due($heat) : null,
        ];
    }
};
?>

<div class="hs-board" wire:poll.30s>
<style global>
    .hs-board { display: grid; grid-template-columns: repeat(auto-fit, minmax(176px, 1fr)); gap: 8px; margin-bottom: 10px; }
    .hs-tile { position: relative; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); padding: 5px 12px 6px; min-width: 0; overflow: hidden; }
    .hs-label { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
    .hs-value { font: 700 20px/1.2 ui-monospace, SFMono-Regular, Menlo, monospace; font-variant-numeric: tabular-nums; letter-spacing: -.01em; white-space: nowrap; }
    .hs-value small { font: 500 12.5px/1 system-ui, sans-serif; color: var(--muted); letter-spacing: 0; }
    .hs-sub { font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hs-sub b { color: var(--text); font-weight: 600; }
    .hs-bar { position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: color-mix(in srgb, var(--border) 60%, transparent); }
    .hs-bar > span { display: block; height: 100%; background: var(--accent); }
    .hs-tile.is-warn .hs-value { color: #b54708; }
    .hs-tile.is-bad .hs-value { color: var(--danger); }
    .hs-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .hs-chip { display: inline-block; vertical-align: middle; margin: -1px 0 0; padding: 1px 8px; border-radius: 6px; font: 700 10.8px/1.5 system-ui, sans-serif; letter-spacing: 0; }
    .hs-readings { text-align: left; font: 600 12.5px/1.3 ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--text); white-space: nowrap; }
</style>

{{-- Metal charged, worked out from the charges. --}}
<div class="hs-tile">
    <div class="hs-label">Metal charged</div>
    <div class="hs-value">{{ $metal > 0 ? \App\Support\Formats::number($metal, fmod($metal, 1.0) == 0.0 ? 0 : 1) : '—' }} @if ($metal > 0)<small>kg</small>@endif</div>
    @if ($heat->target_metal_weight_kg > 0)
        <div class="hs-sub" @if ($rate) title="Melting rate: {{ ['live' => 'measured on this heat', 'recorded' => 'furnace recorded average', 'rated' => 'furnace rated speed'][$rate['source']] }}" @endif><b>{{ \App\Support\Formats::number($metal / (float) $heat->target_metal_weight_kg * 100, 0) }}%</b> of {{ \App\Support\Formats::number((float) $heat->target_metal_weight_kg, 0) }}@if ($rate) @ <b>{{ \App\Support\Formats::number($rate['rate'], 0) }}</b> kg/hr @else kg @endif</div>
        <div class="hs-bar"><span style="width: {{ min(100, round($metal / (float) $heat->target_metal_weight_kg * 100)) }}%"></span></div>
    @else
        <div class="hs-sub">From the charges recorded</div>
    @endif
</div>

{{-- The latest test: the verdict sits top right, the readings and time are left-aligned. --}}
<div class="hs-tile {{ $latest && $latest['result'] === 'FAIL' ? 'is-bad' : '' }}">
    <div class="hs-head">
        <div class="hs-label">Latest test</div>
        @if ($latest)<span class="hs-chip status {{ $latest['result'] === 'PASS' ? 'status-completed' : ($latest['result'] === 'FAIL' ? 'status-cancelled' : '') }}">{{ $latest['result'] === 'UNJUDGED' ? 'NOT JUDGED' : $latest['result'] }}</span>@endif
    </div>
    @if ($latest)
        <div class="hs-readings">C {{ $latest['c'] !== null ? \App\Support\Formats::number($latest['c'], 2) : '—' }} · CE {{ $latest['ce'] !== null ? \App\Support\Formats::number($latest['ce'], 2) : '—' }} · Si {{ $latest['si'] !== null ? \App\Support\Formats::number($latest['si'], 2) : '—' }}</div>
        <div class="hs-sub"><b>{{ $latest['at']->displayAt() }}</b> · {{ $latest['at']->diffForHumans(null, true, true) }} ago @if ($latest['reference']) · {{ $latest['reference'] }}@endif</div>
    @else
        <div class="hs-value">—</div>
        <div class="hs-sub">No test yet @if ($open)· <button type="button" class="link-btn" style="padding:0" data-quick="qd-record-test">record one</button>@endif</div>
    @endif
</div>

{{-- Running time: counted in the browser from the start of the run. --}}
@if ($run['start'])
<div class="hs-tile" title="Furnace running time for this heat, from {{ $run['start']->displayAt() }}"
    x-data="{ since: {{ $run['start']->getTimestampMs() }}, running: {{ $run['running'] ? 'true' : 'false' }}, now: Date.now(), end: {{ ($run['end'] ?? null) ? $run['end']->getTimestampMs() : 'null' }},
        get s() { return Math.max(0, Math.floor(((this.running ? this.now : (this.end ?? this.now)) - this.since) / 1000)); },
        get text() { const p = (n) => String(n).padStart(2, '0'); return this.running ? Math.floor(this.s / 3600) + ':' + p(Math.floor(this.s / 60) % 60) + ':' + p(this.s % 60) : Math.floor(this.s / 3600) + 'h ' + p(Math.floor(this.s / 60) % 60) + 'm'; } }"
    x-init="if (running) setInterval(() => now = Date.now(), 1000)">
    <div class="hs-label">{{ $run['running'] ? 'Running time' : 'Ran for' }}</div>
    <div class="hs-value" x-text="text">@if ($run['running']){{ \App\Support\Formats::clock((int) $run['start']->diffInSeconds(now(), true)) }}@else{{ intdiv($ranMinutes = (int) $run['start']->diffInMinutes($run['end'] ?? now(), true), 60) }}h {{ str_pad((string) ($ranMinutes % 60), 2, '0', STR_PAD_LEFT) }}m @endif</div>
    <div class="hs-sub">Started <b>{{ $run['start']->displayAt() }}</b></div>
</div>
@endif

{{-- Time remaining: counted down in the browser to the finish estimate; the estimate itself is recalculated on each refresh. --}}
@if ($open && $run['running'])
<div class="hs-tile {{ $finish && $finish['finish_at']->isPast() ? 'is-bad' : ($finish ? 'is-warn' : '') }}" title="Estimated finish, from the target metal and the melting rate{{ $finish ? ' ('.$finish['label'].')' : '' }}"
    @if ($finish) x-data="{ at: {{ $finish['finish_at']->getTimestampMs() }}, now: Date.now(),
        get left() { return Math.floor((this.at - this.now) / 1000); },
        get text() { const p = (n) => String(n).padStart(2, '0'), s = Math.abs(this.left); return (this.left < 0 ? '-' : '') + Math.floor(s / 3600) + ':' + p(Math.floor(s / 60) % 60) + ':' + p(s % 60); } }"
    x-init="setInterval(() => now = Date.now(), 1000)" :class="left < 0 ? 'is-bad' : 'is-warn'" @endif>
    @if ($finish)
        <div class="hs-label" x-text="left < 0 ? 'Overdue by' : 'Time remaining'">{{ $finish['finish_at']->isPast() ? 'Overdue by' : 'Time remaining' }}</div>
        <div class="hs-value" x-text="text">{{ \App\Support\Formats::clock(abs((int) now()->diffInSeconds($finish['finish_at'], false))) }}</div>
        <div class="hs-sub">Finish about <b>{{ $finish['finish_at']->displayAt() }}</b> · {{ \App\Support\Formats::number($finish['remaining_kg'], 0) }} kg to go</div>
    @else
        <div class="hs-label">Time remaining</div>
        <div class="hs-value">—</div>
        <div class="hs-sub">{{ $heat->target_metal_weight_kg > 0 ? 'No melting speed on record' : 'Set a target metal weight' }}</div>
    @endif
</div>
@endif

{{-- Charges added, the last one and the next. --}}
<div class="hs-tile {{ $next && $next['state'] === 'due' ? 'is-bad' : '' }}">
    <div class="hs-label">Charges added</div>
    <div class="hs-value">{{ $done }} <small>of {{ $planned }}</small></div>
    <div class="hs-sub">
        @if ($lastRound !== null)Last charge <b>{{ $lastRound === 0 ? 'start-up' : '#'.$lastRound }}</b> {{ $lastAt->displayAt() }}@else No charge yet @endif
        @if ($next && $next['state'] === 'due') · <b style="color:var(--danger)">{{ $next['round'] === 0 ? 'start-up' : '#'.$next['round'] }} due</b>
        @elseif ($next && $next['due_at']) · next <b>{{ $next['round'] === 0 ? 'start-up' : '#'.$next['round'] }}</b> {{ $next['due_at']->displayAt() }}
        @elseif ($done >= $planned && $done > 0) · all in @endif
    </div>
    <div class="hs-bar"><span style="width: {{ $planned > 0 ? min(100, round($done / $planned * 100)) : 0 }}%"></span></div>
</div>

</div>
