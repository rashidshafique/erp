<?php

use App\Models\Heat;
use App\Services\Production\ChargeSchedule;
use App\Services\Production\HeatEta;
use App\Services\Production\HeatSamples;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * What matters about a heat at a glance, above its tabs: how long it has run and how long is left, the charges in and the last
 * one, the metal charged (worked out from those charges), the next charge, and the latest test. The clocks count in the browser;
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
<style>
    .hs-board { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-bottom: 14px; }
    .hs-tile { border: 1px solid var(--border); border-radius: 12px; background: var(--surface); padding: 10px 14px 11px; min-width: 0; }
    .hs-label { font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
    .hs-value { font: 700 28px/1.2 ui-monospace, SFMono-Regular, Menlo, monospace; font-variant-numeric: tabular-nums; letter-spacing: -.01em; margin-top: 2px; }
    .hs-value small { font: 500 14px/1 system-ui, sans-serif; color: var(--muted); letter-spacing: 0; }
    .hs-sub { margin-top: 3px; font-size: 12.5px; color: var(--muted); min-height: 17px; }
    .hs-sub b { color: var(--text); font-weight: 600; }
    .hs-bar { height: 6px; border-radius: 3px; background: color-mix(in srgb, var(--border) 70%, transparent); overflow: hidden; margin-top: 7px; }
    .hs-bar > span { display: block; height: 100%; border-radius: 3px; background: var(--accent); }
    .hs-tile.is-warn .hs-value { color: #b54708; }
    .hs-tile.is-bad .hs-value { color: var(--danger); }
    .hs-tile.is-good .hs-value { color: #18794e; }
    .hs-chip { display: inline-block; vertical-align: middle; margin-right: 6px; padding: 2px 8px; border-radius: 6px; font: 700 12px/1.4 system-ui, sans-serif; letter-spacing: 0; }
    .hs-readings { font: 600 13px/1.3 ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--text); }
</style>

{{-- Running time: counted in the browser from the start of the run. --}}
@if ($run['start'])
<div class="hs-tile" title="Furnace running time for this heat, from {{ $run['start']->displayAt() }}"
    x-data="{ since: {{ $run['start']->getTimestampMs() }}, running: {{ $run['running'] ? 'true' : 'false' }}, now: Date.now(), end: {{ ($run['end'] ?? null) ? $run['end']->getTimestampMs() : 'null' }},
        get s() { return Math.max(0, Math.floor(((this.running ? this.now : (this.end ?? this.now)) - this.since) / 1000)); },
        get text() { const p = (n) => String(n).padStart(2, '0'); return Math.floor(this.s / 3600) + ':' + p(Math.floor(this.s / 60) % 60) + ':' + p(this.s % 60); } }"
    x-init="if (running) setInterval(() => now = Date.now(), 1000)">
    <div class="hs-label">{{ $run['running'] ? 'Running time' : 'Ran for' }}</div>
    <div class="hs-value" x-text="text">{{ \App\Support\Formats::clock((int) $run['start']->diffInSeconds($run['end'] ?? now(), true)) }}</div>
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
        <div class="hs-sub">{{ $heat->target_metal_weight_kg > 0 ? 'No melting speed on record for this furnace' : 'Set a target metal weight to get an estimate' }}</div>
    @endif
</div>
@endif

{{-- Charges added and the last one. --}}
<div class="hs-tile">
    <div class="hs-label">Charges added</div>
    <div class="hs-value">{{ $done }} <small>of {{ $planned }}</small></div>
    <div class="hs-bar"><span style="width: {{ $planned > 0 ? min(100, round($done / $planned * 100)) : 0 }}%"></span></div>
    <div class="hs-sub">
        @if ($next && $next['state'] === 'due')<b style="color:var(--danger)">{{ $next['round'] === 0 ? 'Start-up charge' : 'Charge '.$next['round'] }} is due</b> since {{ $next['due_at']->displayAt() }}
        @elseif ($next && $next['due_at'])Next: <b>{{ $next['round'] === 0 ? 'start-up charge' : 'charge '.$next['round'] }}</b> at {{ $next['due_at']->displayAt() }}
        @elseif ($bedIn || $done > 0)@if ($done >= $planned)All planned charges are in @else Start-up charge in @endif
        @else No charge recorded yet @endif
    </div>
</div>
<div class="hs-tile">
    <div class="hs-label">Last charge</div>
    <div class="hs-value">@if ($lastRound === null)—@elseif ($lastRound === 0)<small>Start-up</small>@else #{{ $lastRound }}@endif</div>
    <div class="hs-sub">
        @if ($lastAt)<b>{{ $lastAt->displayAt() }}</b> · {{ $lastAt->diffForHumans(null, true, true) }} ago @else Nothing charged yet @endif
    </div>
</div>

{{-- Metal charged, worked out from the charges. --}}
<div class="hs-tile">
    <div class="hs-label">Metal charged</div>
    <div class="hs-value">{{ $metal > 0 ? \App\Support\Formats::number($metal, fmod($metal, 1.0) == 0.0 ? 0 : 1) : '—' }} @if ($metal > 0)<small>kg</small>@endif</div>
    @if ($heat->target_metal_weight_kg > 0)
        <div class="hs-bar"><span style="width: {{ min(100, round($metal / (float) $heat->target_metal_weight_kg * 100)) }}%"></span></div>
        <div class="hs-sub"><b>{{ \App\Support\Formats::number($metal / (float) $heat->target_metal_weight_kg * 100, 0) }}%</b> of the {{ \App\Support\Formats::number((float) $heat->target_metal_weight_kg, 0) }} kg target</div>
    @else
        <div class="hs-sub">From the charges recorded</div>
    @endif
</div>

{{-- The latest test. --}}
<div class="hs-tile {{ $latest && $latest['result'] === 'FAIL' ? 'is-bad' : '' }}">
    <div class="hs-label">Latest test</div>
    @if ($latest)
        <div class="hs-value" style="font-size:22px">
            <span class="hs-chip status {{ $latest['result'] === 'PASS' ? 'status-completed' : ($latest['result'] === 'FAIL' ? 'status-cancelled' : '') }}">{{ $latest['result'] === 'UNJUDGED' ? 'NOT JUDGED' : $latest['result'] }}</span>
        </div>
        <div class="hs-readings">C {{ $latest['c'] !== null ? \App\Support\Formats::number($latest['c'], 2) : '—' }} · CE {{ $latest['ce'] !== null ? \App\Support\Formats::number($latest['ce'], 2) : '—' }} · Si {{ $latest['si'] !== null ? \App\Support\Formats::number($latest['si'], 2) : '—' }}</div>
        <div class="hs-sub"><b>{{ $latest['at']->displayAt() }}</b> · {{ $latest['at']->diffForHumans(null, true, true) }} ago @if ($latest['reference']) · {{ $latest['reference'] }}@endif</div>
    @else
        <div class="hs-value">—</div>
        <div class="hs-sub">No test yet @if ($open)· <button type="button" class="link-btn" style="padding:0" data-quick="qd-record-test">record one</button>@endif</div>
    @endif
</div>
</div>
