
@extends('layouts.app')

@section('title', 'Heat '.$heat->heat_number)

@section('content')
<div class="page-heading">
    <div>
        @php
            $furnaceState = $heat->furnace?->currentState();
            $run = $heat->furnaceRun();
            $runMinutes = $heat->furnaceRunMinutes();
        @endphp
        <h1 class="heat-title">{{ $heat->heat_number }}<x-heat-light :heat="$heat" after />
        </h1>
        <p>
            <!-- {{ $heat->furnace?->name ?? 'Unknown furnace' }}
            @unless ($heat->furnace)· {{ $heat->process_type }}@endunless
            · Heat {{ strtolower(str_replace('_', ' ', $heat->status)) }} -->
            @if ($heat->furnace)
                <span class="status" title="Furnace type">{{ ucfirst(strtolower($heat->furnace->furnace_type)) }}</span>
                <span class="status" title="Furnace">{{ $heat->furnace->code }}</span>
                <span class="status {{ ['on' => 'status-in-progress', 'idle' => 'status-completed', 'warn' => 'status-on-hold', 'off' => 'status-cancelled'][$furnaceState['tone']] }}" title="Furnace status">{{ $furnaceState['label'] }}@if ($furnaceState['heat'] && $furnaceState['heat']->id !== $heat->id) <a href="{{ route('heats.show', $furnaceState['heat']) }}">{{ $furnaceState['heat']->heat_number }}</a>@endif</span>
            @endif
            
        </p>
    </div>
    @if ($run['running'] || $runMinutes !== null)
    <div class="heat-clock" aria-label="Furnace timers">
        <div class="clock-cell" title="Furnace running time for this heat{{ $run['start'] ? ', from '.$run['start']->displayDateTime() : '' }}">
            <div class="clock-label">{{ $run['running'] ? 'Running time' : 'Ran for' }}</div>
            <div class="clock-val" id="heat-run" @if ($run['running']) data-run-since="{{ $run['start']->toIso8601String() }}" @endif>{{ $run['running'] ? \App\Support\Formats::clock((int) $run['start']->diffInSeconds(now(), true)) : \App\Support\Formats::duration($runMinutes) }}</div>
        </div>
        @if ($run['running'])
            <div class="clock-cell clock-eta" id="heat-eta" data-eta-url="{{ route('heats.eta', $heat) }}" @unless ($finish) hidden @endunless
                title="Estimated finish, from the heat's target metal and the melting rate ({{ $finish['basis'] ?? '' }}). Refreshed every minute.">
                <div class="clock-label" data-eta-label>{{ ($finish['overdue'] ?? false) ? 'Overdue, was due' : 'Finish ETA' }}</div>
                <div class="clock-val" data-eta-time>{{ $finish['time'] ?? '' }}</div>
                <div class="clock-sub" data-eta-in>{{ $finish ? (($finish['overdue'] ?? false) ? '' : 'in '.$finish['in']) : '' }}</div>
            </div>
        @endif
    </div>
    @endif
    <div class="actions">
        <a class="btn" href="{{ route('heats.index') }}">Back to heats</a>
        @if (! in_array($heat->status, ['CLOSED', 'CANCELLED'], true))
            <button type="button" class="btn" data-quick="qd-record-test">Record test</button>
            @unless ($heat->hold_reason)<button type="button" class="btn" data-quick="qd-hold">Put on hold</button>@endunless
        @endif
    </div>
</div>
<style>
    .heat-clock { display: flex; gap: 0; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); margin-left: auto; margin-right: 16px; align-self: center; }
    .clock-cell { padding: 8px 20px; min-width: 150px; }
    .clock-cell + .clock-cell { border-left: 1px solid var(--border); }
    .clock-label { font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
    .clock-val { font: 700 30px/1.15 ui-monospace, SFMono-Regular, Menlo, monospace; font-variant-numeric: tabular-nums; letter-spacing: -.01em; }
    .clock-eta .clock-val { color: #b54708; }
    .clock-sub { font-size: 12px; color: var(--muted); min-height: 15px; }
    @media (max-width: 900px) { .heat-clock { order: 3; margin: 8px 0 0; width: 100%; } .clock-cell { flex: 1; min-width: 0; } }
    .link-btn { appearance: none; border: 0; background: transparent; padding: 0 0 0 6px; font: inherit; font-size: 12px; font-weight: 600; color: var(--accent); cursor: pointer; }
    .link-btn:hover { text-decoration: underline; }
    .heat-title { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .heat-title .status { font-size: 13px; border-radius: 6px; }
    .heat-title .status:not([class*="status-"]) { background: color-mix(in srgb, var(--border) 45%, transparent); color: var(--text); }
</style>
<script>
(function () {
    var run = document.getElementById('heat-run'), eta = document.getElementById('heat-eta');
    function pad(n) { return String(n).padStart(2, '0'); }
    // Running time: counted in the browser from the start time, so it moves every second without asking the server.
    if (run && run.dataset.runSince) {
        var since = new Date(run.dataset.runSince);
        (function tick() {
            var s = Math.max(0, Math.floor((Date.now() - since) / 1000));
            run.textContent = Math.floor(s / 3600) + ':' + pad(Math.floor(s / 60) % 60) + ':' + pad(s % 60);
            setTimeout(tick, 1000 - (Date.now() % 1000));
        })();
    }
    // Target metal: the figure turns into a small form in place.
    var tView = document.querySelector('[data-target-view]'), tForm = document.querySelector('[data-target-form]');
    if (tView && tForm) {
        var openTarget = function (open) { tView.hidden = open; tForm.hidden = !open; if (open) tForm.querySelector('input').select(); };
        tView.querySelector('[data-target-edit]').addEventListener('click', function () { openTarget(true); });
        tForm.querySelector('[data-target-cancel]').addEventListener('click', function () { openTarget(false); });
        if (document.querySelector('.error-text') && {{ $errors->has('target_metal_weight_kg') ? 'true' : 'false' }}) openTarget(true);
    }
    // Total charged: shows how many charges the typed weight is worth.
    var totalForm = document.querySelector('[data-per-round]');
    if (totalForm) {
        var per = parseFloat(totalForm.dataset.perRound), totalInput = totalForm.querySelector('[data-total-input]'), hint = totalForm.querySelector('[data-total-hint]');
        totalInput.addEventListener('input', function () {
            var kg = parseFloat(totalInput.value);
            if (!kg || !per) return;
            var n = Math.round(kg / per);
            hint.textContent = n < 1 ? 'Less than one charge (' + Math.round(per) + ' kg of metal).' : kg.toLocaleString() + ' kg is ' + n + (n === 1 ? ' charge' : ' charges') + ' of ' + Math.round(per) + ' kg of metal.';
        });
    }
    // Charge due: the banner appears by itself when the scheduled time arrives; the minute poll below keeps the time current.
    var due = document.getElementById('charge-due');
    if (due) {
        (function watch() {
            var at = Date.parse(due.dataset.dueAt);
            due.hidden = !(at && Date.now() >= at);
            setTimeout(watch, 1000);
        })();
    }
    // Finish estimate: asked of the server once a minute, since it depends on the metal tapped or poured so far.
    if (eta) {
        function refresh() {
            fetch(eta.dataset.etaUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (!d) return;
                    if (due && d.charge_due_at) due.dataset.dueAt = d.charge_due_at;
                    if (!d.running) { location.reload(); return; }
                    eta.hidden = !d.eta;
                    if (d.eta) {
                        eta.querySelector('[data-eta-label]').textContent = d.eta.overdue ? 'Overdue, was due' : 'Finish ETA';
                        eta.querySelector('[data-eta-time]').textContent = d.eta.time;
                        eta.querySelector('[data-eta-in]').textContent = d.eta.overdue ? '' : 'in ' + d.eta.in;
                        eta.title = 'Estimated finish, from the heat\'s target metal and the melting rate (' + d.eta.basis + '). Refreshed every minute.';
                    }
                })
                .catch(function () {});
        }
        setInterval(refresh, 60000);
    }
})();
</script>

@unless (in_array($heat->status, ['CLOSED', 'CANCELLED'], true))
    @include('partials.record-test-dialog', ['id' => 'qd-record-test', 'heat' => $heat])
@endunless
@if ($heat->hold_reason)
    <div class="alert alert-error"><strong>{{ $heat->lightLabel() }}</strong> since {{ $heat->held_at?->displayDateTime() }}: {{ $heat->hold_note }}
        <form method="POST" action="{{ route('heats.resume', $heat) }}" style="display:inline">@csrf<button class="btn" type="submit">Take off hold</button></form></div>
@elseif (! in_array($heat->status, ['CLOSED', 'CANCELLED'], true))
    @include('partials.quick-dialog', ['id' => 'qd-hold', 'title' => 'Put heat '.$heat->heat_number.' on hold', 'action' => route('heats.hold', $heat), 'submit' => 'Put on hold', 'fields' => 'heats._quick-hold'])
@endif

@php $nextCharge = $schedule['next'] ?? null; @endphp
@if ($nextCharge && $nextCharge['state'] !== 'waiting' && ! $heat->hold_reason && ! in_array($heat->status, ['CLOSED', 'CANCELLED'], true))
    <div class="alert alert-warning" id="charge-due" data-due-at="{{ $nextCharge['due_at']?->toIso8601String() }}" @if ($nextCharge['state'] !== 'due') hidden @endif>
        <strong>{{ $nextCharge['round'] === 0 ? 'The start-up charge is due' : 'Charge '.$nextCharge['round'].' is due' }}</strong>
        (scheduled {{ $nextCharge['due_at']?->format('H:i') }}): {{ $dueLines }}.
        <form method="POST" action="{{ route('heats.charge-rounds.confirm', $heat) }}" style="display:inline">@csrf
            <button class="btn btn-primary" type="submit" title="Records these weights from the recipe, with the oldest accepted lot of each material, as charged now">Confirm {{ $nextCharge['round'] === 0 ? 'start-up charge' : 'charge '.$nextCharge['round'] }} from the recipe</button></form>
        @if (($pastCount ?? 0) >= 2)
            <form method="POST" action="{{ route('heats.charge-rounds.backfill', $heat) }}" style="display:inline" onsubmit="return confirm(@js('Record the '.$pastCount.' charges due since the heat started, each at its scheduled time, from the recipe? They will be marked as filled in from the schedule.'))">@csrf
                <button class="btn" type="submit" title="Records every charge whose time has passed, each at its scheduled time, with the recipe weights">Fill in the {{ $pastCount }} charges since the start</button></form>
        @endif
        <span class="muted small">Different weights? Use the form on the Charge tab.</span>
    </div>
@endif

@if(session('success'))
    <div class="panel">
        <div class="panel-body" style="color:var(--success)">
            {{ session('success') }}
        </div>
    </div>
@endif


<div class="tab-marker" data-tab-start="Overview"></div>
<style>
    .heat-overview { display: grid; grid-template-columns: minmax(0, 1fr) 500px; gap: 16px; align-items: start; }
    .heat-overview > div { display: grid; gap: 16px; min-width: 0; }
    .heat-overview .panel { margin: 0; }
    @media (max-width: 1250px) { .heat-overview { grid-template-columns: 1fr; } }
</style>
<div class="heat-overview">
<div>
<div class="panel">
    <div class="panel-header"><h2>Heat summary</h2></div>
    <div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px">
        <div>
            <div class="muted small">Target metal</div>
            <div data-target-view>
                <strong>
                    {{ $heat->target_metal_weight_kg !== null
                        ? \App\Support\Formats::number((float) $heat->target_metal_weight_kg, 1).' kg'
                        : '—' }}
                </strong>
                @unless (in_array($heat->status, ['CLOSED', 'CANCELLED'], true))<button type="button" class="link-btn" data-target-edit title="Change the target metal weight">Change</button>@endunless
            </div>
            @unless (in_array($heat->status, ['CLOSED', 'CANCELLED'], true))
                <form method="POST" action="{{ route('heats.target.update', $heat) }}" data-target-form hidden style="display:flex;gap:6px;align-items:center;margin-top:2px">@csrf @method('PUT')
                    <input name="target_metal_weight_kg" type="number" step="any" min="0.001" required value="{{ old('target_metal_weight_kg', $heat->target_metal_weight_kg !== null ? rtrim(rtrim((string) $heat->target_metal_weight_kg, '0'), '.') : '') }}" style="width:120px" aria-label="Target metal weight in kg"> <span class="muted small">kg</span>
                    <button class="btn btn-small btn-primary" type="submit">Save</button><button class="btn btn-small" type="button" data-target-cancel>Cancel</button>
                </form>
                @error('target_metal_weight_kg')<div class="error-text">{{ $message }}</div>@enderror
            @endunless
        </div>
        <div>
            <div class="muted small">Rated furnace power</div>
            <strong>
                {{ $heat->furnace?->rated_power_kw !== null
                    ? \App\Support\Formats::number((float) $heat->furnace->rated_power_kw, 1).' kW'
                    : '—' }}
            </strong>
        </div>
        <div>
            <div class="muted small">Furnace operating maximum</div>
            <strong>
                {{ $heat->furnace?->max_operating_power_kw !== null
                    ? \App\Support\Formats::number((float) $heat->furnace->max_operating_power_kw, 1).' kW'
                    : 'Not set' }}
            </strong>
        </div>
        <div>
            <div class="muted small">Heat operating limit</div>
            <strong>
                {{ $heat->operating_power_limit_kw !== null
                    ? \App\Support\Formats::number((float) $heat->operating_power_limit_kw, 1).' kW'
                    : 'Not set' }}
            </strong>
        </div>
    </div>
</div>

@include('heats._balance', ['heat' => $heat])
@if ($heat->isCupola())
    @include('heats._timeline', ['heat' => $heat])
@endif
</div>
<div>
@include('heats._sampling', ['heat' => $heat])
</div>
</div>

<div class="tab-marker" data-tab-start="Chemistry & tests"></div>
@include('heats._chemistry', ['heat' => $heat])
@include('heats._treatment', ['heat' => $heat])
@include('heats._tests', ['heat' => $heat])

<div class="tab-marker" data-tab-start="Charge"></div>
@include('heats._charge-prediction', ['heat' => $heat, 'plannedChargeItems' => $plannedChargeItems])
<div class="panel">
    <div class="panel-header"><h2>Charge recipe and planned materials</h2></div>
    <div class="panel-body">
        @if($heat->chargeBatch)
            <p>
                <strong>{{ $heat->chargeBatch->code }} — {{ $heat->chargeBatch->name }}</strong>
                · Revision {{ $heat->chargeBatch->revision }}
            </p>
            @if($heat->chargeBatch->instructions)
                <p>{{ $heat->chargeBatch->instructions }}</p>
            @endif
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Material</th><th>Role</th><th>Recipe %</th><th>Planned kg</th></tr>
                    </thead>
                    <tbody>
                        @forelse($plannedChargeItems as $item)
                            <tr>
                                <td>{{ $item->material?->code }} — {{ $item->material?->name }}</td>
                                <td>{{ $item->component_role }}@if ($item->charge_stage === 'BED') <span class="muted small">(start-up, once)</span>@elseif ($chargeRounds > 1) <span class="muted small">(× {{ $chargeRounds }} charges)</span>@endif</td>
                                <td>{{ $item->target_percent !== null ? \App\Support\Formats::number((float) $item->target_percent, 4).'%' : '—' }}</td>
                                <td>
                                    {{ $item->planned_calculated_qty_kg !== null
                                        ? \App\Support\Formats::number((float) $item->planned_calculated_qty_kg, 3)
                                        : 'Basis required' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">This recipe has no components.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @php
                $actualByMaterial = $heat->materialConsumptions
                    ->groupBy('material_id')
                    ->map(fn ($entries) => (float) $entries->sum('actual_qty_kg'));

                $reconciliationRows = $plannedChargeItems
                    ->groupBy('material_id')
                    ->map(function ($items, $materialId) use ($actualByMaterial) {
                        $first = $items->first();
                        $planned = $items->contains(fn ($item) => $item->planned_calculated_qty_kg === null)
                            ? null
                            : (float) $items->sum('planned_calculated_qty_kg');

                        return [
                            'material_id' => $materialId,
                            'material' => $first->material,
                            'planned' => $planned,
                            'actual' => (float) ($actualByMaterial[$materialId] ?? 0),
                        ];
                    });

                $unplannedMaterialIds = $heat->materialConsumptions
                    ->pluck('material_id')
                    ->unique()
                    ->diff($reconciliationRows->keys());

                foreach ($unplannedMaterialIds as $materialId) {
                    $entry = $heat->materialConsumptions->firstWhere('material_id', $materialId);
                    $reconciliationRows->push([
                        'material_id' => $materialId,
                        'material' => $entry?->material,
                        'planned' => null,
                        'actual' => (float) $actualByMaterial[$materialId],
                    ]);
                }
            @endphp

            <h3 style="margin-top:24px">Planned vs actual charge</h3>
            <p class="muted small">
                Variance is actual minus planned. Positive means over plan; negative means under plan.
                Internal returns count as positive charge inputs. Materials not in the recipe are shown separately.
            </p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Planned kg</th>
                            <th>Actual kg</th>
                            <th>Variance kg</th>
                            <th>Variance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reconciliationRows as $row)
                            @php
                                $variance = $row['planned'] !== null
                                    ? $row['actual'] - $row['planned']
                                    : null;
                                $variancePercent = $row['planned'] !== null && $row['planned'] != 0
                                    ? ($variance / $row['planned']) * 100
                                    : null;
                            @endphp
                            <tr>
                                <td>
                                    {{ $row['material']?->code ?? 'Unknown' }}
                                    — {{ $row['material']?->name ?? 'Material unavailable' }}
                                    @if($row['planned'] === null)
                                        <span class="muted small">(No comparable plan)</span>
                                    @endif
                                </td>
                                <td>{{ $row['planned'] !== null ? \App\Support\Formats::number($row['planned'], 3) : '—' }}</td>
                                <td>{{ \App\Support\Formats::number($row['actual'], 3) }}</td>
                                <td>{{ $variance !== null ? \App\Support\Formats::number($variance, 3) : '—' }}</td>
                                <td>{{ $variancePercent !== null ? \App\Support\Formats::number($variancePercent, 2).'%' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No recipe or actual charge entries available for comparison.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted">No charge recipe is assigned to this heat.</p>
        @endif
    </div>
</div>


@if($heat->chargeBatch && !in_array($heat->status, ['CLOSED', 'CANCELLED'], true) && !$chargeIsPosted && $nextRoundItems->isNotEmpty())
<div class="panel">
    <div class="panel-header">
        <h2>{{ $nextRound === 0 ? 'Start-up (bed) charge' : 'Charge '.$nextRound.($chargeRounds > 1 ? ' of '.$chargeRounds : '') }}</h2>
        <span class="muted small">{{ $roundsDone }} of {{ $chargeRounds }} charge(s) recorded</span>
    </div>
    <div class="panel-body">
        @include('heats._charge-schedule', ['schedule' => $schedule])
        @if ($heat->actual_start_at && $schedule['metal_per_round_kg'] && ! in_array($heat->status, ['CLOSED', 'CANCELLED'], true))
            <form method="POST" action="{{ route('heats.charge-rounds.total', $heat) }}" class="form-inline" style="margin:4px 0 14px" data-per-round="{{ $schedule['metal_per_round_kg'] }}"
                onsubmit="return confirm('Add the missing charges for this total, at their scheduled times, with the recipe quantities scaled to match? They will be marked as filled in from the total.')">@csrf
                <div class="field"><label for="total_charged_kg">Total metal charged so far (kg)</label>
                    <input id="total_charged_kg" name="total_charged_kg" type="number" step="any" min="0.001" required value="{{ old('total_charged_kg') }}" style="width:150px" data-total-input></div>
                <button class="btn" type="submit">Add the missing charges</button>
                <span class="muted small" data-total-hint>A charge is {{ \App\Support\Formats::number($schedule['metal_per_round_kg'], 0) }} kg of metal. Type the total from the scale and the missing charges are added to match.</span>
            </form>
            @error('total_charged_kg')<div class="alert alert-error">{{ $message }}</div>@enderror
        @endif
        <p class="muted small">The recipe quantities are filled in. Change a weight if it was different, then record. The oldest accepted lot of each material is used unless you pick another.</p>
        <form method="POST" action="{{ route('heats.charge-rounds.store', $heat) }}">
            @csrf
            <input type="hidden" name="charge_round" value="{{ $nextRound }}">
            <div class="table-wrap"><table>
                <thead><tr><th>Material</th><th>Quantity kg</th><th>Lot</th></tr></thead>
                <tbody>
                @foreach($nextRoundItems as $i => $item)
                    @php $lots = $acceptedLots->where('material_id', $item->material_id); @endphp
                    <tr>
                        <td>{{ $item->material?->code }} — {{ $item->material?->name }}<input type="hidden" name="lines[{{ $i }}][material_id]" value="{{ $item->material_id }}"></td>
                        <td><input name="lines[{{ $i }}][actual_qty_kg]" type="number" step="any" min="0" style="width:110px" value="{{ old('lines.'.$i.'.actual_qty_kg', $item->per_charge_qty_kg !== null ? round($item->per_charge_qty_kg, 3) : '') }}"></td>
                        <td><select name="lines[{{ $i }}][material_lot_id]" style="min-width:160px"><option value="">Oldest lot</option>@foreach($lots as $lot)<option value="{{ $lot->id }}">{{ $lot->lot_number }}</option>@endforeach</select></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="form-grid" style="margin-top:10px">
                <div class="field"><label for="round_by">Recorded by *</label><input id="round_by" name="recorded_by" required maxlength="100" value="{{ old('recorded_by', auth()->user()?->name ?? $heat->operator_name) }}"></div>
                <div class="field"><label for="round_at">Charged at *</label><input id="round_at" name="consumed_at" type="datetime-local" required value="{{ old('consumed_at', now()->format('Y-m-d\TH:i')) }}"></div>
            </div>
            @if($errors->has('lines') || $errors->has('charge_round') || $errors->has('material_lot_id'))<div class="alert alert-error">{{ $errors->first('lines') ?: ($errors->first('charge_round') ?: $errors->first('material_lot_id')) }}</div>@endif
            <button class="btn btn-primary" type="submit">{{ $nextRound === 0 ? 'Record start-up charge' : 'Record charge '.$nextRound }}</button>
        </form>
        @if($heat->isCupola() || $chargeRounds > 1)
            <form method="POST" action="{{ route('heats.charge-rounds.update', $heat) }}" class="form-inline" style="margin-top:14px">
                @csrf @method('PUT')
                <div class="field"><label for="charge_rounds">Planned charges on this heat</label><input id="charge_rounds" name="charge_rounds" type="number" min="1" max="500" value="{{ $chargeRounds }}" style="width:90px"></div>
                <button class="btn" type="submit">Update</button>
                <span class="muted small">Worked out from the target metal weight; raise it as the campaign runs on.</span>
            </form>
        @endif
    </div>
</div>
@endif

<div class="panel">
    <div class="panel-header">
        <h2>Actual charge and material costing</h2>
    </div>
    <div class="panel-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin-bottom:20px">
            <div>
                <div class="muted small">Actual charge weight</div>
                <strong>{{ \App\Support\Formats::number($actualChargeQty, 3) }} kg</strong>
            </div>
            <div>
                <div class="muted small">Actual charge material cost</div>
                <strong>PKR {{ \App\Support\Formats::number($actualChargeCost, 2) }}</strong>
            </div>
            <div>
    <div class="muted small">Average charge material cost</div>
    <strong>
        {{ $actualChargeCostPerKg !== null
            ? 'PKR '.\App\Support\Formats::number($actualChargeCostPerKg, 2).'/kg'
            : '—' }}
    </strong>
</div>
<div>
    <div class="muted small">Charge posting status</div>
    <strong>{{ $chargeIsPosted ? 'Posted to inventory ledger' : 'Draft / not posted' }}</strong>
</div>
        </div>

        @if(!in_array($heat->status, ['CLOSED', 'CANCELLED'], true))
            <h3>Record charge material</h3>
            <p class="muted">
                Purchased materials use the cost recorded against an accepted lot.
                Internal returns require a positive transfer cost. Saving a charge does not deduct inventory.
            </p>

            <form method="POST" action="{{ route('heats.charge-entries.store', $heat) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px">
                    <div>
                        <label for="charge_consumption_type">Charge type *</label>
                        <select id="charge_consumption_type" name="consumption_type" required
                                style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                            <option value="RAW_MATERIAL" @selected(old('consumption_type', 'RAW_MATERIAL') === 'RAW_MATERIAL')>Purchased / raw material</option>
                            <option value="INTERNAL_RETURN" @selected(old('consumption_type') === 'INTERNAL_RETURN')>Internal return</option>
                        </select>
                    </div>
                    <div>
                        <label for="charge_material_id">Material *</label>
                        <select id="charge_material_id" name="material_id" required
                                style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                            <option value="">Select material</option>
                            @foreach($activeMaterials as $material)
                                <option value="{{ $material->id }}" @selected(old('material_id') === $material->id)>
                                    {{ $material->code }} — {{ $material->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div id="charge_lot_field">
                        <label for="charge_material_lot_id">Accepted material lot *</label>
                        <select id="charge_material_lot_id" name="material_lot_id"
                                style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                            <option value="">Select lot</option>
                            @foreach($acceptedLots as $lot)
                                <option value="{{ $lot->id }}"
                                        data-material-id="{{ $lot->material_id }}"
                                        @selected(old('material_lot_id') === $lot->id)>
                                    {{ $lot->lot_number }} — {{ $lot->material?->name }} (PKR {{ \App\Support\Formats::number((float) $lot->unit_cost, 4) }}/kg)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="charge_actual_qty">Actual quantity (kg) *</label>
                        <input id="charge_actual_qty" name="actual_qty_kg" type="number"
                               min="0.001" step="any" required value="{{ old('actual_qty_kg') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div id="charge_transfer_cost_field" style="display:none">
                        <label for="charge_transfer_cost">Internal return transfer cost (PKR/kg) *</label>
                        <input id="charge_transfer_cost" name="transfer_cost_per_kg" type="number"
                               min="0.0001" step="any" value="{{ old('transfer_cost_per_kg') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="charge_consumed_at">Charge date and time *</label>
                        <input id="charge_consumed_at" name="consumed_at" type="datetime-local" required
                               value="{{ old('consumed_at', now()->format('Y-m-d\TH:i')) }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="charge_recorded_by">Recorded by *</label>
                        <input id="charge_recorded_by" name="recorded_by" required maxlength="100"
                               value="{{ old('recorded_by', $heat->operator_name) }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="charge_source_reference">Source reference</label>
                        <input id="charge_source_reference" name="source_reference" maxlength="100"
                               value="{{ old('source_reference') }}"
                               placeholder="Return batch, supplier reference..."
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                </div>
                <div style="margin-top:16px">
                    <label for="charge_notes">Notes</label>
                    <textarea id="charge_notes" name="notes" rows="2" maxlength="5000"
                              style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">{{ old('notes') }}</textarea>
                </div>
                <div class="actions" style="margin-top:16px">
                    <button class="btn btn-primary" type="submit">Record charge</button>
                </div>
            </form>
        @else
            <p class="muted">This heat is closed or cancelled; new charge entries are disabled.</p>
        @endif
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Actual charge history</h2>
        <span class="muted small">{{ $heat->materialConsumptions->count() }} entries</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Type</th><th>Material</th><th>Lot / reference</th>
                    <th>Round</th><th>Quantity kg</th><th>Cost PKR/kg</th><th>Total cost PKR</th>
                    <th>Recorded at</th><th>Operator</th>
                </tr>
            </thead>
            <tbody>
                @forelse($heat->materialConsumptions as $entry)
                    @php
                        $entryUnitCost = $entry->actual_qty_kg > 0
                            ? (float) $entry->actual_total_cost / (float) $entry->actual_qty_kg
                            : null;
                    @endphp
                    <tr>
                        <td>{{ $entry->sequence }}</td>
                        <td>{{ str_replace('_', ' ', $entry->consumption_type) }}</td>
                        <td>{{ $entry->material?->code }} — {{ $entry->material?->name }}</td>
                        <td>{{ $entry->materialLot?->lot_number ?? $entry->source_reference ?? '—' }}</td>
                        <td>{{ $entry->charge_round === null ? '—' : ($entry->charge_round === 0 ? 'Start-up' : $entry->charge_round) }}</td>
                        <td>{{ \App\Support\Formats::number((float) $entry->actual_qty_kg, 3) }}</td>
                        <td>{{ $entryUnitCost !== null ? \App\Support\Formats::number($entryUnitCost, 4) : '—' }}</td>
                        <td>{{ \App\Support\Formats::number((float) $entry->actual_total_cost, 2) }}</td>
                        <td>{{ $entry->consumed_at?->displayDateTime() ?? '—' }}</td>
                        <td>{{ $entry->recorded_by }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">No actual charge entries recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@if(!in_array($heat->status, ['CLOSED', 'CANCELLED'], true))
    <div class="panel">
        <div class="panel-header">
            <h2>Post charge to inventory</h2>
        </div>
        <div class="panel-body">
            @if($chargeIsPosted)
                <p style="color:var(--success)">
                    This heat's charge has already been posted.
                    Inventory deductions must not be repeated.
                </p>
                <p>
                    <strong>Posted charge cost:</strong>
                    PKR {{ \App\Support\Formats::number($actualChargeCost, 2) }}
                </p>
            @elseif($heat->materialConsumptions->isEmpty())
                <p class="muted">
                    Record the actual charge materials before posting.
                </p>
            @else
                <p>
                    Posting will deduct purchased materials from their selected
                    lots using FIFO cost layers and create inventory movements.
                    Internal returns will be costed but will not be deducted
                    from purchased-material inventory.
                </p>
                <p class="muted">
                    Verify the quantities, lots and transfer costs in the
                    charge history before proceeding. Posting should be
                    treated as an inventory transaction, not a draft save.
                </p>

                <form method="POST"
                      action="{{ route('heats.charge-postings.store', $heat) }}"
                      onsubmit="return confirm('Post this heat charge to inventory? This action deducts purchased-material stock and must not be repeated.');">
                    @csrf

                    <div style="margin-bottom:16px">
                        <label for="charge_posting_notes">Posting notes</label>
                        <textarea
                            id="charge_posting_notes"
                            name="notes"
                            rows="2"
                            maxlength="5000"
                            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit"
                        >{{ old('notes') }}</textarea>
                    </div>

                    <button class="btn btn-primary" type="submit">
                        Post charge to inventory
                    </button>
                </form>
            @endif
        </div>
    </div>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    const type = document.getElementById('charge_consumption_type');
    const material = document.getElementById('charge_material_id');
    const lot = document.getElementById('charge_material_lot_id');
    const lotField = document.getElementById('charge_lot_field');
    const transferField = document.getElementById('charge_transfer_cost_field');
    const transferCost = document.getElementById('charge_transfer_cost');

    function updateChargeFields() {
        const isReturn = type.value === 'INTERNAL_RETURN';
        lotField.style.display = isReturn ? 'none' : '';
        lot.disabled = isReturn;
        lot.required = !isReturn;
        transferField.style.display = isReturn ? '' : 'none';
        transferCost.required = isReturn;
        transferCost.disabled = !isReturn;

        Array.from(lot.options).forEach(function (option) {
            if (!option.value) return;
            option.hidden = material.value !== '' && option.dataset.materialId !== material.value;
        });

        const selected = lot.options[lot.selectedIndex];
        if (selected && selected.value && selected.hidden) lot.value = '';
    }

    type.addEventListener('change', updateChargeFields);
    material.addEventListener('change', updateChargeFields);
    updateChargeFields();
});
</script>

<div class="tab-marker" data-tab-start="Furnace readings"></div>
@if($heat->furnace?->furnace_type === 'INDUCTION')
    <div class="panel">
        <div class="panel-header"><h2>Record induction meter reading</h2></div>
        <div class="panel-body">
            <p class="muted">
                Enter the values shown on the meter. At least one meter value is required.
                Cumulative kWh is the meter total, not the energy used in this interval.
            </p>
            <form method="POST" action="{{ route('heats.power-readings.store', $heat) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px">
                    <div>
                        <label for="power_recorded_at">Reading date and time *</label>
                        <input id="power_recorded_at" name="recorded_at" type="datetime-local"
                               required value="{{ old('recorded_at', now()->format('Y-m-d\TH:i')) }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="actual_power_kw">Actual power (kW)</label>
                        <input id="actual_power_kw" name="actual_power_kw" type="number"
                               min="0" step="any" value="{{ old('actual_power_kw') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="cumulative_energy_kwh">Cumulative energy (kWh)</label>
                        <input id="cumulative_energy_kwh" name="cumulative_energy_kwh" type="number"
                               min="0" step="any" value="{{ old('cumulative_energy_kwh') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="power_recorded_by">Operator name *</label>
                        <input id="power_recorded_by" name="recorded_by" required maxlength="100"
                               value="{{ old('recorded_by', $heat->operator_name) }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;margin-bottom:16px">
    <div>
        <label for="charge_posted_by">Posted by *</label>
        <input
            id="charge_posted_by"
            name="posted_by"
            required
            maxlength="100"
            value="{{ old('posted_by', $heat->operator_name) }}"
            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit"
        >
    </div>

    <div>
        <label for="charge_posting_notes">Posting notes</label>
        <textarea
            id="charge_posting_notes"
            name="notes"
            rows="2"
            maxlength="2000"
            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit"
        >{{ old('notes') }}</textarea>
    </div>
</div>
                <div class="actions" style="margin-top:18px">
                    <button class="btn btn-primary" type="submit">Save power reading</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($heat->furnace?->furnace_type === 'CUPOLA')
    <div class="panel">
        <div class="panel-header"><h2>Record cupola process reading</h2></div>
        <div class="panel-body">
            <p class="muted">
                Use a consistent parameter code and unit for repeat readings.
                Examples might include blast pressure or temperature, but enter only parameters
                you actually measure at your furnace.
            </p>
            <form method="POST" action="{{ route('heats.process-readings.store', $heat) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px">
                    <div>
                        <label for="process_recorded_at">Reading date and time *</label>
                        <input id="process_recorded_at" name="recorded_at" type="datetime-local"
                               required value="{{ old('recorded_at', now()->format('Y-m-d\TH:i')) }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="parameter_code">Parameter code *</label>
                        <input id="parameter_code" name="parameter_code" required maxlength="60"
                               pattern="[A-Za-z0-9_.-]+"
                               placeholder="e.g. BLAST_PRESSURE"
                               value="{{ old('parameter_code') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="numeric_value">Value *</label>
                        <input id="numeric_value" name="numeric_value" type="number" required
                               step="any" value="{{ old('numeric_value') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="unit">Unit *</label>
                        <input id="unit" name="unit" required maxlength="30"
                               placeholder="e.g. mbar, °C, Hz"
                               value="{{ old('unit') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="process_recorded_by">Operator name *</label>
                        <input id="process_recorded_by" name="recorded_by" required maxlength="100"
                               value="{{ old('recorded_by', $heat->operator_name) }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                </div>
                <div style="margin-top:18px">
                    <label for="process_notes">Notes</label>
                    <textarea id="process_notes" name="notes" rows="2" maxlength="5000"
                              style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">{{ old('notes') }}</textarea>
                </div>
                <div class="actions" style="margin-top:18px">
                    <button class="btn btn-primary" type="submit">Save process reading</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($heat->furnace?->furnace_type === 'INDUCTION')
    <div class="panel">
        <div class="panel-header">
            <h2>Power reading history</h2>
            <span class="muted small">{{ $heat->powerReadings->count() }} readings</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Recorded at</th>
                        <th>Actual power</th>
                        <th>Cumulative energy</th>
                        <th>Operator</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($heat->powerReadings as $reading)
                        <tr>
                            <td>{{ $reading->reading_number }}</td>
                            <td>{{ $reading->recorded_at?->displayDateTime() }}</td>
                            <td>
                                {{ $reading->actual_power_kw !== null
                                    ? \App\Support\Formats::number((float) $reading->actual_power_kw, 3).' kW'
                                    : '—' }}
                            </td>
                            <td>
                                {{ $reading->cumulative_energy_kwh !== null
                                    ? \App\Support\Formats::number((float) $reading->cumulative_energy_kwh, 3).' kWh'
                                    : '—' }}
                            </td>
                            <td>{{ $reading->recorded_by }}</td>
                            <td>{{ $reading->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No power readings recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

@if($heat->furnace?->furnace_type === 'CUPOLA')
    <div class="panel">
        <div class="panel-header">
            <h2>Process reading history</h2>
            <span class="muted small">{{ $heat->processReadings->count() }} readings</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Recorded at</th>
                        <th>Parameter</th>
                        <th>Value</th>
                        <th>Operator</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($heat->processReadings as $reading)
                        <tr>
                            <td>{{ $reading->reading_number }}</td>
                            <td>{{ $reading->recorded_at?->displayDateTime() }}</td>
                            <td>{{ $reading->parameter_code }}</td>
                            <td>{{ $reading->numeric_value }} {{ $reading->unit }}</td>
                            <td>{{ $reading->recorded_by }}</td>
                            <td>{{ $reading->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No process readings recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'heat', 'id' => $heat->id])
@include('attachments._panel', ['type' => 'heat', 'id' => $heat->id])
@include('audit._history', ['type' => 'Heat', 'id' => $heat->id])
@endsection
