@extends('layouts.app')
@section('title', $d->code.' '.$d->title)
@section('breadcrumb')<a href="{{ route('developments.index') }}">Pattern developments</a> / <code>{{ $d->code }}</code> {{ $d->title }} @endsection
@section('content')
@php
    $cls = ['OPEN' => 'status-in-progress', 'DONE' => 'status-completed', 'CANCELLED' => 'status-cancelled'];
    $info = $d->typeInfo(); $stages = $d->stages(); $next = $d->nextStageName();
    $approves = $d->isOpen() && $next && $d->stage_index + 2 === count($stages) ? $d->draftRevision() : null;
    $tools = $d->tools; $many = $tools->count() > 1;
    $stuck = $d->isOpen() ? $d->toolsInTheWay() : collect();
    $gateRule = \App\Domains\Development\Support\DevelopmentRules::get('tool_gate');
    $blocked = $stuck->isNotEmpty() && $gateRule === 'BLOCK';
    $gateNeeds = $d->gateStatus() ? strtolower(config('pattern_developments.tool_statuses.'.$d->gateStatus())) : '';
    $register = app(\App\Domains\Development\Services\ToolRegister::class);
    $kinds = collect(config('pattern_developments.tool_kinds'))->when(! $register->toolingOn(), fn ($k) => $k->except('CORE_BOX'));
    $depth = \App\Domains\Development\Support\DevelopmentRules::get('tool_depth');
    $full = $depth === 'FULL';
    $block = $depth === 'ONE' && $tools->count() === 1 ? $tools->first() : null;   // one pattern, shown as a plain block (design 18c)
    $title = $tools->count() === 1 ? config('pattern_developments.tool_kinds.'.$tools->first()->kind) : 'Tooling'.($many ? ' · '.$tools->count().' tools' : '');
    $badges = ['PATTERN' => 'PT', 'CORE_BOX' => 'CB', 'GAUGE' => 'GA', 'FIXTURE' => 'FX', 'OTHER' => '··'];
    $kindSubs = ['PATTERN' => 'From register or new', 'CORE_BOX' => 'From register or new', 'GAUGE' => 'Checking gauge', 'FIXTURE' => 'Machining or fettling', 'OTHER' => 'Type a name'];
    $trial = $d->gateStatus() === 'TRIAL_PASSED';
    $started = $d->isOpen() ? $d->startedTools() : collect();
    $spent = $started->sum('actual_cost');
    $backStage = $d->stage_index > 0 ? $stages[$d->stage_index - 1] : null;
    $toolName = fn ($t) => config('pattern_developments.tool_kinds.'.$t->kind).' '.($t->registerCode() ?? $t->label);
    $pills = ['NOT_STARTED' => '', 'BUILDING' => 'status-in-progress', 'BUILT' => 'status-in-progress', 'TRIAL_PASSED' => 'status-completed', 'REWORK' => 'status-cancelled', 'NOT_NEEDED' => ''];
@endphp
<style>
    .dv-bar { display: grid; grid-template-columns: repeat({{ count($stages) }}, minmax(0, 1fr)); gap: 6px; margin: 4px 0 18px; }
    .dv-bar > div { display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; color: var(--muted); } .dv-bar > div::before { content: ''; height: 4px; border-radius: 2px; background: var(--border); }
    .dv-bar > div.done::before, .dv-bar > div.now::before { background: var(--accent); } .dv-bar > div.now { color: var(--text); font-weight: 600; }
    .dv-log { list-style: none; margin: 0; padding: 0; } .dv-log li { padding: 8px 0; border-bottom: 1px solid var(--border); display: flex; gap: 12px; justify-content: space-between; } .dv-log .when { color: var(--muted); font-size: 12px; white-space: nowrap; }
    .btn.tl-danger { background: #b3352c; border-color: #b3352c; color: #fff; } .btn.tl-danger:hover { background: #952c24; border-color: #952c24; color: #fff; }
    .tl-badge { flex: none; display: inline-flex; width: 24px; height: 24px; align-items: center; justify-content: center; border-radius: 5px; background: #f0f1f3; color: #1c1d1f; font: 600 9.5px ui-monospace, Menlo, monospace; }
    .tl-tool { display: flex; gap: 10px; align-items: center; } .tl-tool .sub { font-size: 12px; color: #6b6e73; }
    .tl-route { font-size: 11px; padding: 1px 7px; border-radius: 4px; border: 1px solid #c4c7cc; color: #6b6e73; white-space: nowrap; } .tl-route.NEW { border-color: #9db4ec; color: #2f5fd0; } .tl-route.MODIFY { border-color: #e5c78a; color: #94610a; }
    .tl-num { text-align: right; } .tl-total { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px 16px; margin-top: 12px; padding: 10px 16px; border-radius: 8px; background: #f8f8f9; font-size: 12.5px; } .tl-total strong { font-weight: 600; } .tl-total .side { color: #6b6e73; }
    .tl-mini { display: flex; gap: 2px; width: 72px; margin-top: 5px; } .tl-mini i { flex: 1; height: 3px; border-radius: 2px; background: var(--border); } .tl-mini i.on { background: var(--accent); } .tl-mini.rework i.on { background: #b3352c; }
    .tl-menu > summary { list-style: none; cursor: pointer; padding: 0 8px; font-weight: 600; } .tl-menu > summary::-webkit-details-marker { display: none; }
    .tl-pop { position: fixed; z-index: 20; width: 300px; max-height: 70vh; overflow-y: auto; background: #fff; border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 8px 24px rgb(15 23 42 / 16%); padding: 12px; display: flex; flex-direction: column; gap: 10px; }
    .tl-pop .form-grid { grid-template-columns: 1fr 1fr; gap: 8px; } .tl-pop .wide { grid-column: 1 / -1; } .tl-pop hr { border: 0; border-top: 1px solid var(--border); margin: 0; width: 100%; }
    .tl-kinds { display: grid; gap: 4px; grid-column: 1 / -1; } .tl-kinds label { display: flex; gap: 8px; align-items: center; padding: 6px 8px; border: 1px solid var(--border); border-radius: 6px; cursor: pointer; } .tl-kinds label:has(input:checked) { border-color: var(--accent); }
    .tl-back .to-rework { display: none; color: #b3352c; } .tl-back input:checked ~ .to-rework { display: inline; } .tl-back input:checked ~ .keeps { display: none; }
    .tl-list { display: flex; flex-direction: column; gap: 8px; margin: 12px 0; } .tl-list label, .tl-row { display: flex; gap: 8px; align-items: center; justify-content: space-between; }
    .dv-frame { margin: -24px -28px; font-size: 13px; } @media (max-width: 768px) { .dv-frame { margin: -20px -14px; } }
    .dv-top { display: flex; align-items: center; justify-content: space-between; gap: 10px 16px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid #e6e7e9; }
    .dv-crumb { display: flex; align-items: center; gap: 16px; min-width: 0; flex-wrap: wrap; } .dv-crumb > .sep { color: #6b6e73; } .dv-top-actions { display: flex; gap: 8px; align-items: center; }
    .dv-cols { display: grid; grid-template-columns: minmax(0, 1fr) 340px; align-items: stretch; }
    .dv-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 22px; min-width: 0; } .dv-main > .alert { margin: 0; }
    .dv-stage { display: flex; gap: 28px; align-items: flex-start; } .dv-stage .dv-bar { flex: 1; margin: 0; min-width: 0; }
    .dv-move { flex: none; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; max-width: 280px; text-align: right; font-size: 12px; color: #6b6e73; } .dv-move form { margin: 0; } .dv-gate { color: #94610a; } .dv-gate.block { color: #b3352c; }
    .dv-h { font-size: 12px; font-weight: 600; color: #6b6e73; text-transform: uppercase; letter-spacing: .06em; }
    .dv-side { border-left: 1px solid #e6e7e9; padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; } .dv-side-title { font-size: 16px; font-weight: 600; margin-top: 2px; }
    .dv-side-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; } .dv-side-h { font-size: 12px; font-weight: 600; color: #6b6e73; text-transform: uppercase; letter-spacing: .06em; }
    .dv-links { margin-top: 8px; } .dv-links > div { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid #e6e7e9; text-align: right; } .dv-links > div:last-child { border-bottom: 0; } .dv-links > div > span:first-child { text-align: left; }
    @media (max-width: 1100px) { .dv-cols { grid-template-columns: 1fr; } .dv-side { border-left: 0; border-top: 1px solid #e6e7e9; } .dv-top, .dv-main { padding-left: 16px; padding-right: 16px; } .dv-stage { flex-direction: column; } .dv-move { align-items: flex-start; text-align: left; } }
</style>
@include('partials.quick-dialog-assets')
<div class="dv-frame">
<div class="dv-top">
    <div class="dv-crumb"><a href="{{ route('developments.index') }}" style="font-weight:600;color:inherit">Pattern development</a><span class="sep">/</span><span><code>{{ $d->code }}</code> {{ $d->title }}</span><span class="status {{ $cls[$d->status] ?? '' }}">{{ $d->status === 'OPEN' ? $d->stageName() : \App\Domains\Development\Models\PatternDevelopment::STATUSES[$d->status] }}</span></div>
    <div class="dv-top-actions"><a class="btn" href="{{ route('developments.index') }}">All developments</a>
        @if ($d->isOpen() && $backStage)<button type="button" class="btn" data-quick="dvBack">Back to {{ $backStage }}</button>@endif</div>
</div>
<div class="dv-cols"><div class="dv-main">
@if (session('gate_warn'))
<div class="alert alert-warning"><div>{{ session('gate_warn') }}</div><div>Moving on is logged as an override with your name. {{ $d->gateStays() }}</div>
    <form method="POST" action="{{ route('developments.advance', $d) }}" style="margin-top:8px">@csrf<input type="hidden" name="override" value="1"><button class="btn btn-primary" type="submit">Move anyway</button> <a class="btn" href="{{ route('developments.show', $d) }}">Cancel</a></form></div>
@endif
@error('gate')<div class="alert alert-error">{{ $message }}</div>@enderror
@if ($d->isOpen() && $d->stage_index > 0)
<dialog class="qd" id="dvBack" aria-labelledby="dvBackTitle" @error('reason') data-reopen='{}' @enderror>
<form method="POST" action="{{ route('developments.back', $d) }}">@csrf
    <div class="qd-head"><h2 id="dvBackTitle">Send back to {{ $backStage }}</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
    <div class="qd-body">
        <div class="alert alert-error qd-errors" role="alert" @error('reason') @else hidden @enderror>@error('reason'){{ $message }}@enderror</div>
        <div class="field"><label for="dvBackReason">Reason</label><textarea id="dvBackReason" name="reason" maxlength="300" required rows="3" placeholder="e.g. trial found shrinkage at the boss">{{ old('reason') }}</textarea></div>
        @if ($many)<div class="tl-list tl-back tl-pad"><strong>Tools to rework</strong>
            @foreach ($tools as $t)<label><input type="checkbox" name="tools[]" value="{{ $t->id }}" @checked(in_array($t->id, old('tools', $trial && $t->status === 'BUILT' ? [$t->id] : [])))><span style="flex:1">{{ $toolName($t) }}</span><span class="muted keeps">{{ $t->statusName() }}</span><span class="to-rework">{{ $t->statusName() }} → Rework</span></label>@endforeach
            <span class="muted small">Ticked tools go to Rework. Unticked tools keep their status.</span></div>
        @elseif ($tools->count() === 1)<p>{{ $toolName($tools->first()) }} goes to Rework.</p>@endif
    </div>
    <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">Send back</button></div>
</form>
</dialog>
@endif
<div class="dv-stage"><div class="dv-bar">@foreach ($stages as $i => $name)<div class="{{ $d->status === 'DONE' || $i < $d->stage_index ? 'done' : ($i === $d->stage_index ? 'now' : '') }}">{{ $name }}@if ($many && ($g = \App\Domains\Development\Models\PatternDevelopment::gateFor($name)))<span style="font-weight:400;display:block">{{ $tools->filter(fn ($t) => $t->reached($g))->count() }} of {{ $tools->count() }} {{ $g === 'BUILT' ? 'built' : 'passed trial' }}@if ($g === 'TRIAL_PASSED' && $tools->where('status', 'REWORK')->isNotEmpty()) · {{ $tools->where('status', 'REWORK')->count() }} rework @endif</span>@endif</div>@endforeach</div>
@if ($d->isOpen() && $next)<div class="dv-move"><form method="POST" action="{{ route('developments.advance', $d) }}">@csrf<button class="btn btn-primary" type="submit" @disabled($blocked)>Move to {{ $next }}</button></form>
    @if ($approves)<span>Also approves revision {{ $approves->revision }}</span>@endif
    @if ($stuck->isNotEmpty() && $gateRule !== 'OFF' && ! session('gate_warn'))<span class="dv-gate {{ $blocked ? 'block' : '' }}">{{ $d->gateMessage() }}@if ($blocked) Set by a rule in <a href="{{ route('settings.process-depth.edit') }}">settings</a>.@endif</span>@endif</div>@endif
</div>
@php
    $amount = fn ($v) => $d->cost_currency ? $d->cost_currency.' '.\App\Support\Formats::number($v, 2) : \App\Support\Formats::money($v);
    $quotedSum = $tools->whereNotNull('quoted_cost')->sum('quoted_cost');
    $actualSum = $tools->whereNotNull('actual_cost')->isEmpty() ? null : $tools->sum('actual_cost');
    $over = $d->quoted_tooling_cost !== null && $actualSum !== null ? $actualSum - $d->quoted_tooling_cost : null;
@endphp
<div class="panel" style="overflow:visible"><div class="panel-header"><h2>{{ $title }}</h2>
    @if ($d->isOpen() && ! $block)<details style="position:relative" id="tlAdd"><summary class="btn">+ Add tool</summary>
        <form method="POST" action="{{ route('developments.tools.store', $d) }}" class="form-grid" style="position:absolute;right:0;z-index:5;background:#fff;border:1px solid var(--border);border-radius:8px;padding:14px;width:420px;grid-template-columns:1fr 1fr;gap:10px">@csrf
            <div class="tl-kinds">@foreach ($kinds as $k => $l)<label><input type="radio" name="kind" value="{{ $k }}" @checked($loop->first)><span class="tl-badge">{{ $badges[$k] }}</span><span>{{ $l }} <span class="muted small">{{ $kindSubs[$k] }}</span></span></label>@endforeach</div>
            <div class="field field-full" style="grid-column:1/-1"><label for="tl-label">Name</label><input id="tl-label" name="label" maxlength="200" required placeholder="e.g. Volute core box"></div>
            <div class="field"><label for="tl-route">Route</label><select id="tl-route" name="route">@foreach (config('pattern_developments.tool_routes') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            @if ($full)
            <div class="field"><label for="tl-by">Made by</label><select id="tl-by" name="made_by">@foreach (config('pattern_developments.pattern_by') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="tl-maker">Maker</label><input id="tl-maker" name="maker" maxlength="150"></div>
            <div class="field"><label for="tl-pays">Pays</label><select id="tl-pays" name="who_pays"><option value="">—</option>@foreach (config('pattern_developments.who_pays') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="tl-quoted">Quoted</label><input id="tl-quoted" name="quoted_cost" type="number" step="0.01" min="0"></div>
            <div class="field"><label for="tl-due">Due</label><input id="tl-due" name="due_on" type="date"></div>
            @endif
            <div style="grid-column:1/-1"><button class="btn btn-primary" type="submit">Add tool</button></div>
        </form></details>@endif</div>
<div class="panel-body">
    @if ($block)
    <form method="POST" action="{{ route('developments.tools.update', [$d, $block]) }}">@csrf @method('PUT')
        <fieldset @disabled(! $d->isOpen()) style="border:0;margin:0;padding:0;min-width:0">
        <div class="form-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
            <div class="field"><label for="bl-by">Pattern by</label><select id="bl-by" name="made_by">@foreach (config('pattern_developments.pattern_by') as $k => $l)<option value="{{ $k }}" @selected($block->made_by === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="bl-maker">Pattern maker</label><input id="bl-maker" name="maker" maxlength="150" value="{{ $block->maker }}"></div>
            <div class="field"><label for="bl-quoted">Quoted</label><input id="bl-quoted" name="quoted_cost" type="number" step="0.01" min="0" value="{{ $block->quoted_cost }}"></div>
            <div class="field"><label for="bl-actual">Actual pattern cost</label><input id="bl-actual" name="actual_cost" type="number" step="0.01" min="0" value="{{ $block->actual_cost }}"></div>
        </div>
        @if ($d->isOpen())<button class="btn" type="submit" style="margin-top:10px">Save</button>@endif
        </fieldset></form>
    @elseif ($tools->isEmpty())<p class="muted">No tools yet. Add the pattern, core box or gauge this development will make or change.</p>
    @else
    <div style="overflow-x:auto"><table class="table"><thead><tr><th>Tool</th><th>Route</th>@if ($full)<th>Made by</th><th>Pays</th><th class="tl-num">Quoted</th><th class="tl-num">Actual</th><th>Due</th>@endif<th>Status</th><th></th></tr></thead><tbody>
    @foreach ($tools as $t)
        @php $canRegister = $register->canCreate($t); @endphp
        <tr>
            <td><div class="tl-tool"><span class="tl-badge">{{ $badges[$t->kind] ?? '··' }}</span><div>
                <div>{{ config('pattern_developments.tool_kinds.'.$t->kind) }}
                    @if ($t->pattern) <a href="{{ route('patterns.show', $t->pattern) }}"><code>{{ $t->pattern->code }}</code></a>@endif
                    @if ($t->coreBox) <a href="{{ route('core-boxes.show', $t->coreBox) }}"><code>{{ $t->coreBox->code }}</code></a>@endif
                    @if ($t->route === 'NEW') <span class="muted">(new)</span>@endif</div>
                <div class="sub">{{ $t->label }}@if ($canRegister) · not in register yet @endif @if ($t->coreBox?->status === 'INACTIVE') · out of service until built @endif</div>
                @if ($t->replacesPattern ?? $t->replacesCoreBox)@php $old = $t->replacesPattern ?? $t->replacesCoreBox; @endphp<div class="sub">replaces {{ $old->code }}@if ($t->retired_at), retired {{ $t->retired_at->displayDate() }}@endif</div>@endif
            </div></div></td>
            <td><span class="tl-route {{ $t->route }}">{{ config('pattern_developments.tool_routes.'.$t->route) }}</span></td>
            @if ($full)
            <td>{{ config('pattern_developments.pattern_by.'.$t->made_by) }}@if ($t->maker)<br><span class="muted small">{{ $t->maker }}</span>@endif</td>
            <td>{{ $t->who_pays ? config('pattern_developments.who_pays.'.$t->who_pays) : '—' }}</td>
            <td class="tl-num">{{ $t->quoted_cost !== null ? $amount($t->quoted_cost) : '—' }}</td>
            <td class="tl-num">{{ $t->actual_cost !== null ? $amount($t->actual_cost) : '—' }}</td>
            <td>{{ $t->due_on?->displayDate() ?? '—' }}</td>
            @endif
            <td><span class="status {{ $pills[$t->status] ?? '' }}">{{ $t->statusName() }}</span>
                @if ($many)<div class="tl-mini {{ $t->status === 'REWORK' ? 'rework' : '' }}" aria-hidden="true">@foreach (range(1, 3) as $step)<i class="{{ $t->steps() >= $step ? 'on' : '' }}"></i>@endforeach</div>@endif
                @if ($t->status_note)<br><span class="muted small">{{ $t->status_note }}</span>@endif
                @if ($d->isOpen() && $canRegister && $t->status === 'BUILT')<br><form method="POST" action="{{ route('developments.tools.register', [$d, $t]) }}" style="display:inline">@csrf<button class="btn-link" type="submit" style="border:0;background:none;padding:0;color:var(--accent);cursor:pointer;font-size:12px">Add to register</button></form>@endif</td>
            <td style="text-align:right">@if ($d->isOpen())<details class="tl-menu"><summary aria-label="Actions for {{ $t->label }}">⋯</summary><div class="tl-pop">
                <form method="POST" action="{{ route('developments.tools.update', [$d, $t]) }}">@csrf @method('PUT')
                    <div class="form-grid">
                        <div class="field wide"><label for="st-{{ $t->id }}">Change status</label><select id="st-{{ $t->id }}" name="status">@foreach (config('pattern_developments.tool_statuses') as $k => $l)<option value="{{ $k }}" @selected($t->status === $k)>{{ $l }}</option>@endforeach</select></div>
                        @if ($full)
                        <div class="field"><label for="ac-{{ $t->id }}">Record cost</label><input id="ac-{{ $t->id }}" name="actual_cost" type="number" step="0.01" min="0" value="{{ $t->actual_cost }}" aria-label="Actual cost of {{ $t->label }}"></div>
                        <div class="field"><label for="qc-{{ $t->id }}">Quoted</label><input id="qc-{{ $t->id }}" name="quoted_cost" type="number" step="0.01" min="0" value="{{ $t->quoted_cost }}"></div>
                        <div class="field"><label for="mk-{{ $t->id }}">Maker</label><input id="mk-{{ $t->id }}" name="maker" maxlength="150" value="{{ $t->maker }}"></div>
                        <div class="field"><label for="du-{{ $t->id }}">Due</label><input id="du-{{ $t->id }}" name="due_on" type="date" value="{{ $t->due_on?->format('Y-m-d') }}"></div>
                        @endif
                        <div class="wide"><button class="btn btn-primary" type="submit">Save</button></div>
                    </div></form>
                @if ($canRegister)<hr>
                <form method="POST" action="{{ route('developments.tools.register', [$d, $t]) }}">@csrf<button class="btn" type="submit" style="width:100%">Create register record</button></form>
                @php $linkable = $t->kind === 'CORE_BOX' ? $registerBoxes : $registerPatterns; @endphp
                @if ($linkable->isNotEmpty())<form method="POST" action="{{ route('developments.tools.link', [$d, $t]) }}" style="display:flex;gap:6px">@csrf
                    <select name="record" required aria-label="Register record for {{ $t->label }}" style="flex:1;min-width:0"><option value="">Link to register record…</option>@foreach ($linkable as $r)<option value="{{ ($t->kind === 'CORE_BOX' ? 'box:' : 'pattern:').$r->id }}">{{ $r->code }} {{ $r->name }}</option>@endforeach</select><button class="btn" type="submit">Link</button></form>@endif
                @endif
                <hr>
                <form method="POST" action="{{ route('developments.tools.destroy', [$d, $t]) }}" onsubmit="return confirm('Remove this tool?')">@csrf @method('DELETE')<button class="btn tl-danger" type="submit" style="width:100%" aria-label="Remove {{ $t->label }}">Remove tool</button></form>
            </div></details>@endif</td>
        </tr>
    @endforeach
    </tbody></table></div>
    @endif
    @if ($tools->isNotEmpty() && $depth !== 'LIST')
    <div class="tl-total"><span>Total quoted <strong>{{ $quotedSum ? $amount($quotedSum) : '—' }}</strong> · actual <strong>{{ $actualSum !== null ? $amount($actualSum) : '—' }}@if ($actualSum !== null && $d->isOpen() && $tools->whereNull('actual_cost')->isNotEmpty()) so far @endif</strong></span>
        @if ($d->quoted_tooling_cost !== null)<span class="side">Allowed in quote {{ $amount($d->quoted_tooling_cost) }}@if ($over !== null) · <span style="color:{{ $over > 0 ? '#b3352c' : '#1d7048' }}">{{ $over > 0 ? 'over by '.$amount($over) : 'within by '.$amount(-$over) }}</span>@endif</span>@endif</div>
    @endif
</div></div>
<script>
document.addEventListener('toggle', function (e) {
    var menu = e.target;
    if (!menu.classList || !menu.classList.contains('tl-menu') || !menu.open) { return; }
    document.querySelectorAll('.tl-menu[open]').forEach(function (m) { if (m !== menu) { m.open = false; } });
    var box = menu.querySelector('summary').getBoundingClientRect(), pop = menu.querySelector('.tl-pop');
    pop.style.left = Math.max(8, box.right - pop.offsetWidth) + 'px';
    pop.style.top = Math.max(8, Math.min(box.bottom + 4, window.innerHeight - pop.offsetHeight - 8)) + 'px';
}, true);
document.addEventListener('click', function (e) {
    document.querySelectorAll('.tl-menu[open]').forEach(function (m) { if (!m.contains(e.target)) { m.open = false; } });
});
</script>
<div><div class="dv-h" style="margin-bottom:10px">Log</div>
    <form method="POST" action="{{ route('developments.notes', $d) }}" style="display:flex;gap:8px;margin-bottom:10px">@csrf
        <input name="text" maxlength="2000" required placeholder="Add a note: what was agreed or sent" style="flex:1" aria-label="Note"><button class="btn" type="submit">Add note</button></form>
    <ul class="dv-log">@foreach ($d->events as $ev)<li><span>{{ $ev->text }}<span class="muted small"> · {{ $ev->user_name }}</span></span><span class="when">{{ $ev->created_at->displayDateTime() }}</span></li>@endforeach</ul>
</div>
@if ($d->isOpen())<div><button type="button" class="btn tl-danger" data-quick="dvCancel">Cancel development</button></div>@endif
</div>
<aside class="dv-side">
    <div><code class="muted">{{ $d->code }}</code><div class="dv-side-title">{{ $d->title }}</div>
        <div class="muted">{{ $d->customer?->name ?? '—' }}@if ($d->enquiry) · from <a href="{{ route('enquiries.show', $d->enquiry) }}"><code>{{ $d->enquiry->code }}</code></a>@endif</div></div>
    <div class="dv-side-grid">
        <div><div class="muted small">Drawing</div><div>@if ($item?->drawing_rev)Rev {{ $item->drawing_rev }}@if ($item->drawing_ref) · {{ $item->drawing_ref }}@endif @elseif ($item?->drawing_ref){{ $item->drawing_ref }}@else — @endif</div></div>
        <div><div class="muted small">Metal</div><div>{{ $item?->material_text ?: '—' }}</div></div>
        <div><div class="muted small">Tools</div><div>{{ $tools->isEmpty() ? '—' : $d->toolSummary() }}</div></div>
        <div><div class="muted small">Owner</div><div>{{ $d->owner_name ?: '—' }}</div></div>
        <div><div class="muted small">Type</div><div>{{ $info['name'] }}</div></div>
        <div><div class="muted small">Due</div><div>{{ $d->due_on?->displayDate() ?? '—' }}</div></div>
        @if ($d->reason)<div><div class="muted small">Reason</div><div>{{ config('pattern_developments.reasons.'.$d->reason) }}</div></div>@endif
        @if ($d->who_pays)<div><div class="muted small">Who pays</div><div>{{ config('pattern_developments.who_pays.'.$d->who_pays) }}</div></div>@endif
    </div>
    @if ($d->drawing_change)<div><div class="muted small">Drawing change</div><div>{{ $d->drawing_change }}</div></div>@endif
    @if ($d->notes)<div><div class="muted small">Notes</div><div>{{ $d->notes }}</div></div>@endif
    <div class="ei-preview" style="height:110px;border-radius:8px;border:1px solid #e0e1e4;background:repeating-linear-gradient(135deg,#fff 0 8px,#f1f2f4 8px 16px);display:flex;align-items:center;justify-content:center;overflow:hidden">
        @if ($preview && $preview->isImage())<img src="{{ route('attachments.download', $preview) }}" alt="{{ $preview->original_name }}" style="max-width:100%;max-height:100%;object-fit:contain">
        @elseif ($preview)<iframe src="{{ route('attachments.download', $preview) }}" title="{{ $preview->original_name }}" style="width:100%;height:100%;border:0;background:#fff"></iframe>
        @else<code style="font-size:11.5px;color:#6b6e73;background:#fff;padding:4px 8px;border-radius:4px">{{ $d->enquiry ? 'No drawing yet' : 'latest drawing preview' }}</code>@endif
    </div>
    <div><div class="dv-side-h">Linked</div>
        <div class="dv-links">
            <div><span>Feasibility</span><span class="muted">@if ($signed)<a href="{{ route('feasibility.show', $item) }}">signed</a> {{ $signed->signed_at->displayDate() }}@elseif ($item)<a href="{{ route('feasibility.show', $item) }}">not signed yet</a>@else — @endif</span></div>
            <div><span>Tooling charge</span><span class="muted">{{ $d->quoted_tooling_cost !== null ? $amount($d->quoted_tooling_cost) : '—' }}</span></div>
            <div><span>Part record</span><span class="muted">@if ($d->product)<a href="{{ route('products.show', $d->product) }}">{{ $d->product->code }}</a>@elseif (in_array($d->type, ['NEW_PART', 'NEW_PRODUCT'], true))Created on approval @else — @endif</span></div>
        </div></div>
</aside></div></div>
@if ($d->isOpen())
@php $cancelErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'decisions')); @endphp
<dialog class="qd" id="dvCancel" aria-labelledby="dvCancelTitle" @if ($cancelErrors->isNotEmpty()) data-reopen='{}' @endif>
<form method="POST" action="{{ route('developments.cancel', $d) }}">@csrf
    <div class="qd-head"><h2 id="dvCancelTitle">Cancel {{ $d->code }}?</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
    <div class="qd-body">
        <div class="alert alert-error qd-errors" role="alert" @if ($cancelErrors->isEmpty()) hidden @endif>@foreach ($cancelErrors->flatten() as $message)<div>{{ $message }}</div>@endforeach</div>
        <div class="field"><label for="dvCancelReason">Reason</label><input id="dvCancelReason" name="reason" maxlength="300" value="{{ old('reason') }}"></div>
        @if ($started->isNotEmpty())
        <div class="tl-list"><strong>Started tools · {{ $started->count() }} of {{ $tools->count() }}</strong>
            @foreach ($started as $t)
                @php $choices = collect(config('pattern_developments.cancel_decisions'))->when(! $t->registerCode(), fn ($c) => $c->except('KEEP')); @endphp
                <div class="tl-row"><span>{{ $toolName($t) }}<br><span class="muted small">{{ $t->actual_cost ? $amount($t->actual_cost).' spent' : 'No spend yet' }}@if ($t->who_pays) · {{ strtolower(config('pattern_developments.who_pays.'.$t->who_pays)) }} pays @endif · {{ $t->statusName() }}</span></span>
                    <select name="decisions[{{ $t->id }}]" required aria-label="What happens to {{ $t->label }}"><option value="">Decide…</option>@foreach ($choices as $k => $l)<option value="{{ $k }}" @selected(old('decisions.'.$t->id) === $k)>{{ $l }}</option>@endforeach</select></div>
            @endforeach
            @if ($spent > 0)<p style="margin:0">{{ $amount($spent) }} spent stays on the development for billing. Raise any tooling invoice before closing.</p>@endif
        </div>
        @endif
        @if ($tools->count() > $started->count())<p class="muted small">Tools not started are dropped.</p>@endif
    </div>
    <div class="qd-foot"><button type="button" class="btn" data-close>Keep open</button><button class="btn tl-danger" type="submit">Cancel development</button></div>
</form>
</dialog>
@endif
@endsection
