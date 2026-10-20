@extends('layouts.app')

@section('title', 'Record moulding run')
@section('breadcrumb', 'Moulding runs / New run')

{{-- Three step columns after the production-floor design. Panels whose data the app does not record yet show a dashed
     "Not recorded yet" placeholder (.rm-ph) and post nothing; every field that is saved keeps its original name. --}}
@php
    $revisionData = $patternRevisions->mapWithKeys(fn ($r) => [$r->id => [
        'code' => $r->shortCode(),
        'name' => $r->pattern?->name ?? 'Pattern',
        'type' => $r->pattern ? str_replace('_', ' ', $r->pattern->pattern_type) : null,
        'cavities' => $r->cavities_per_mould,
        'layers' => $r->layers_per_mould,
        'station' => $r->pattern?->preferred_moulding_station_id,
        'sand' => $r->pattern?->sand_system,
    ]]);
    $stationSand = $stations->pluck('default_sand_system', 'id');
    $orderData = $productionOrders->mapWithKeys(fn ($o) => [$o->id => [
        'number' => $o->production_order_number,
        'status' => $o->status,
        'productId' => $o->product_id,
        'code' => $o->product?->code ?? '—',
        'product' => $o->product?->name ?? 'Product unavailable',
        'revision' => $o->productRevision?->revision ?? '—',
        'customer' => $o->customerLabel(),
        'qty' => \App\Support\Formats::number((float) $o->planned_qty, 0),
        'uom' => $o->uom,
    ]]);
@endphp

@section('content')
<style>
    .rm-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:14px; }
    .rm-crumb { font:600 11px ui-monospace, Menlo, monospace; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); }
    .rm-crumb b { color:var(--accent); }
    .rm-title { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:2px 0 0; }
    .rm-title h1 { margin:0; }
    .rm-chip { font:600 11px ui-monospace, Menlo, monospace; padding:3px 8px; border-radius:6px; background:#e8eef5; color:#2b4a6b; }
    .rm-chip.live { background:#e6f4ec; color:var(--success); }
    .rm-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .rm-strip { display:flex; flex-wrap:wrap; gap:8px 22px; padding:10px 14px; margin-bottom:16px; background:var(--surface); border:1px solid var(--border); border-radius:10px; font:600 11.5px ui-monospace, Menlo, monospace; text-transform:uppercase; letter-spacing:.03em; }
    .rm-strip span { color:var(--muted); }
    .rm-strip em { font-style:normal; color:var(--muted); font-weight:500; text-transform:none; }
    .rm-cols { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:16px; align-items:start; }
    .rm-step { display:flex; justify-content:space-between; align-items:center; gap:8px; }
    .rm-step i { font:600 10px ui-monospace, Menlo, monospace; font-style:normal; text-transform:uppercase; background:#24313a; color:#fff; padding:3px 7px; border-radius:5px; white-space:nowrap; }
    .rm-ph { border:1px dashed var(--border); border-radius:8px; padding:8px 10px; color:var(--muted); background:#fafbfb; font-size:12.5px; }
    .rm-ph small { display:block; font-size:10.5px; text-transform:uppercase; letter-spacing:.05em; }
    .rm-block { border:1px solid var(--border); border-radius:10px; padding:12px; margin-top:14px; }
    .rm-block-title { margin-bottom:8px; }
    .rm-block .rm-order { margin-top:0; }
    .rm-block .rm-facts { margin:10px 0 0; }
    .rm-facts { display:grid; grid-template-columns:1fr 1fr; gap:10px 14px; padding:12px; background:#f6f8f9; border-radius:8px; margin:12px 0; }
    .rm-facts dt, .rm-label { font:600 10.5px ui-monospace, Menlo, monospace; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); margin:0; }
    .rm-facts dd { margin:2px 0 0; font-weight:600; }
    .rm-order { padding:12px; border:1px solid var(--border); border-radius:8px; margin-top:10px; background:#fafbfb; }
    .rm-order .big { font-size:17px; font-weight:700; margin:4px 0; }
    .rm-tool { display:grid; grid-template-columns:130px 1fr; gap:6px 10px; font-size:13px; margin:10px 0; }
    .rm-tool dt { color:var(--muted); margin:0; } .rm-tool dd { margin:0; font-weight:600; }
    .rm-sub { margin:14px 0 6px; }
    .rm-two { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .rm-three { display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; }
    .rm-quick { float:right; font-size:11px; font-weight:600; color:var(--accent); background:none; border:0; cursor:pointer; padding:0; }
    .rm-elapsed { display:flex; justify-content:space-between; gap:10px; padding:10px 12px; background:#eaf1f8; border-radius:8px; margin:12px 0; font-size:13px; }
    .rm-elapsed strong { font-family:ui-monospace, Menlo, monospace; }
    .rm-tiles { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px; }
    .rm-tile { padding:10px; border-radius:8px; background:#eaf1f8; }
    .rm-tile.ok { background:#e6f4ec; } .rm-tile.bad { background:#fbe9e7; }
    .rm-tile .rm-label { margin-bottom:6px; }
    .rm-tile small { display:block; margin-top:4px; font-size:11px; color:var(--muted); }
    .rm-bar { height:9px; border-radius:6px; background:#eceff1; overflow:hidden; margin:8px 0 4px; }
    .rm-bar div { height:100%; width:0; background:var(--accent); transition:width .2s; }
    .rm-yield { display:flex; justify-content:space-between; align-items:baseline; }
    .rm-yield strong { font:700 18px ui-monospace, Menlo, monospace; }
    .rm-pipe { margin-top:16px; }
    .rm-pipeline { display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:8px; }
    .rm-stage { padding:9px 10px; border-radius:8px; background:#f1f4f6; font-size:11.5px; border-top:3px solid transparent; }
    .rm-stage b { display:block; font:600 11px ui-monospace, Menlo, monospace; text-transform:uppercase; }
    .rm-stage.on { background:#fff; border:1px solid var(--border); border-top:3px solid var(--accent); }
    .rm-stage span { color:var(--muted); text-transform:uppercase; font-size:10.5px; }
    .rm-tally { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .rm-pill { font:600 12.5px ui-monospace, Menlo, monospace; padding:6px 10px; border-radius:6px; background:#eaf1f8; }
    .rm-pill.ok { background:#e6f4ec; } .rm-pill.bad { background:#fbe9e7; color:var(--danger); }
    .rm-foot { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-top:16px; padding:14px; background:var(--surface); border:1px solid var(--border); border-radius:10px; }
    @media (max-width: 1100px) { .rm-cols { grid-template-columns:1fr; } .rm-pipeline { grid-template-columns:repeat(3, 1fr); } }
</style>

<form method="POST" action="{{ route('moulding-runs.store') }}" id="run-form">
    @csrf

    <div class="rm-head">
        <div>
            <div class="rm-crumb">Production › Moulding runs › <b>New entry (log run)</b></div>
            <div class="rm-title">
                <h1>Record moulding run</h1>
                <span class="rm-chip" id="rm-shift-chip">{{ strtoupper($defaults['shift']) }}</span>
                <span class="rm-chip live">● Live station feed</span>
            </div>
        </div>
        <div class="rm-actions">
            <a href="{{ route('moulding-runs.index') }}" class="btn">← Back to runs</a>
            <button type="button" class="btn" id="rm-draft" title="Saves the run as In progress">Save draft</button>
            <button type="button" class="btn" disabled title="Barcode tags are not available yet">Print barcode tags</button>
            <button type="submit" class="btn btn-primary">Commit moulding run</button>
        </div>
    </div>

    <div class="rm-strip">
        <span>Moulding cell: <em>not recorded yet</em></span>
        <span>Sand moisture: <em>not recorded yet</em></span>
        <span>Squeeze pressure: <em>not recorded yet</em></span>
        <span>Barcode scanner: <em>not connected</em></span>
    </div>

    <div class="rm-cols">
        {{-- Step 1 --}}
        <div>
            <section class="panel">
                <div class="panel-header rm-step"><h2>Production traveler &amp; tooling</h2><i>Step 1</i></div>
                <div class="panel-body">
                    <div class="field">
                        <label for="production_order_id">Production order <span class="required">*</span></label>
                        <select id="production_order_id" name="production_order_id" required>
                            <option value="">Select production order</option>
                            @foreach ($productionOrders as $order)
                                <option value="{{ $order->id }}" @selected((string) $selectedProductionOrderId === (string) $order->id)>
                                    {{ $order->production_order_number }} — {{ $order->product?->name ?? 'Product not set' }} ({{ $order->status }})
                                </option>
                            @endforeach
                        </select>
                        <div class="help">{{ $productionOrders->count() }} open {{ \Illuminate\Support\Str::plural('order', $productionOrders->count()) }}. Only open production orders are listed.</div>
                        @error('production_order_id')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="rm-block">
                        <div class="rm-label rm-block-title">Product</div>
                        <div id="production-order-summary" class="rm-order" style="display:none">
                        <div style="display:flex;justify-content:space-between;gap:8px">
                            <div>
                                <strong id="po-summary-number" class="code"></strong> <span id="po-summary-status" class="status"></span>
                                <div class="big" id="po-summary-product"></div>
                                <div class="code muted small" id="po-summary-code"></div>
                                <div class="muted small">Customer: <span id="po-summary-customer">—</span></div>
                            </div>
                            <div style="text-align:right"><div class="rm-label">Target qty</div><strong style="font-size:18px" id="po-summary-qty">—</strong> <span class="muted small" id="po-summary-uom"></span></div>
                        </div>
                    </div>
                        <dl class="rm-facts">
                            <div><dt>Revision</dt><dd id="po-summary-revision" class="code">—</dd></div>
                            <div><dt>Material grade</dt><dd class="muted">Not recorded yet</dd></div>
                            <div><dt>Target pour temp</dt><dd class="muted">Not recorded yet</dd></div>
                        </dl>
                    </div>

                    <div class="rm-block">
                        <div class="rm-label rm-block-title">Pattern &amp; tooling</div>
                        <div class="form-grid" data-progressive="3">
                        <div class="field">
                            <label for="pattern_revision_id">Pattern revision</label>
                            <select id="pattern_revision_id" name="pattern_revision_id">
                                <option value="">Not specified</option>
                                @foreach ($patternRevisions as $revision)
                                    <option value="{{ $revision->id }}" @selected(old('pattern_revision_id') === $revision->id)>
                                        {{ $revision->pattern?->name ?? 'Pattern' }} — {{ $revision->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('pattern_revision_id')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="mould_configuration_id">Mould configuration</label>
                            <select id="mould_configuration_id" name="mould_configuration_id">
                                <option value="">Not specified</option>
                                @foreach ($mouldConfigurations as $configuration)
                                    <option value="{{ $configuration->id }}" @selected(old('mould_configuration_id') === $configuration->id)>
                                        {{ $configuration->name }} ({{ $configuration->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('mould_configuration_id')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                    </div>
                        <dl class="rm-tool">
                            <dt>Pattern tooling</dt><dd><span id="rm-pattern">—</span><div class="code muted small" id="rm-pattern-code"></div></dd>
                            <dt>Cavities / layers</dt><dd id="rm-cavities">—</dd>
                            <dt>Cores per mould</dt><dd class="muted">Not recorded yet</dd>
                            <dt>Tooling bay / rack</dt><dd class="muted">Not recorded yet</dd>
                            <dt>Core box tool</dt><dd class="muted">Not recorded yet</dd>
                            <dt>Flask box dimension</dt><dd class="muted">Not recorded yet</dd>
                        </dl>
                    </div>

                    <div class="rm-label rm-sub">Mould sand control spec</div>
                    <div class="rm-three">
                        <div class="rm-ph"><small>AFS fineness</small>—</div>
                        <div class="rm-ph"><small>Hardness B</small>—</div>
                        <div class="rm-ph"><small>Permeability</small>—</div>
                    </div>
                    <div class="help">Sand lab readings are not recorded yet.</div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-body" style="display:flex;justify-content:space-between;align-items:center;gap:10px">
                    <div><strong>Pattern plate life</strong><div class="muted small">Cycles used against the tooling limit</div></div>
                    <span class="rm-ph">Not recorded yet</span>
                </div>
            </section>
        </div>

        {{-- Step 2 --}}
        <div>
            <section class="panel">
                <div class="panel-header rm-step"><h2>Run details &amp; shift execution</h2><i>Step 2</i></div>
                <div class="panel-body">
                    <div class="rm-two">
                        <div class="field">
                            <label for="run_date">Run date <span class="required">*</span></label>
                            <input id="run_date" type="date" name="run_date" value="{{ old('run_date', $defaults['run_date']) }}" required>
                            @error('run_date')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="shift">Shift allocation</label>
                            <select id="shift" name="shift">
                                <option value="">Select shift</option>
                                @foreach (['Morning', 'Evening', 'Night', 'General'] as $shift)
                                    <option value="{{ $shift }}" @selected(old('shift', $defaults['shift']) === $shift)>{{ $shift }}</option>
                                @endforeach
                            </select>
                            @error('shift')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="started_at">Started at <button type="button" class="rm-quick" data-set="started_at" data-time="08:00">Set 08:00</button></label>
                            <input id="started_at" type="datetime-local" name="started_at" value="{{ old('started_at') }}">
                            @error('started_at')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="completed_at">Completed at <button type="button" class="rm-quick" data-set="completed_at" data-time="now">Set now</button></label>
                            <input id="completed_at" type="datetime-local" name="completed_at" value="{{ old('completed_at') }}">
                            @error('completed_at')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="rm-elapsed"><span class="rm-label">Gross elapsed run time</span><strong id="rm-elapsed">—</strong></div>

                    <div class="field">
                        <label for="moulding_method">Moulding line &amp; method</label>
                        <select id="moulding_method" name="moulding_method">
                            <option value="">Select method</option>
                            @foreach (['Manual', 'Semi-automatic', 'Automatic'] as $method)
                                <option value="{{ $method }}" @selected(old('moulding_method', $defaults['moulding_method']) === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                        @error('moulding_method')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="sand_system">Active sand preparation loop</label>
                        <select id="sand_system" name="sand_system">
                            <option value="">Select sand system</option>
                            @foreach (['Greensand', 'Alphaset', 'Resin-coated sand', 'Sheera', 'Other'] as $system)
                                <option value="{{ $system }}" @selected(old('sand_system', $defaults['sand_system']) === $system)>{{ $system }}</option>
                            @endforeach
                        </select>
                        @error('sand_system')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="rm-label rm-sub">Crew &amp; heat routing</div>
                    <div class="rm-two">
                        <div class="field">
                            <label for="operator_name">Primary operator</label>
                            <input id="operator_name" name="operator_name" value="{{ old('operator_name') }}" maxlength="150" placeholder="Operator / moulder">
                            @error('operator_name')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="team_name">Assigned crew</label>
                            <input id="team_name" name="team_name" value="{{ old('team_name') }}" maxlength="150" placeholder="Team">
                            @error('team_name')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="field" style="margin-top:6px">
                        <label for="moulding_station_id">Assigned station</label>
                        <select id="moulding_station_id" name="moulding_station_id">
                            <option value="">Not specified</option>
                            @foreach ($stations as $station)
                                <option value="{{ $station->id }}" @selected(old('moulding_station_id') === $station->id)>{{ $station->code }} — {{ $station->name }}</option>
                            @endforeach
                        </select>
                        @error('moulding_station_id')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="rm-label rm-sub">Assigned pouring heat allocation</div>
                    <div class="rm-ph"><small>Heat</small>No heat is linked to a moulding run yet</div>
                </div>
            </section>
        </div>

        {{-- Step 3 --}}
        <div>
            <section class="panel">
                <div class="panel-header rm-step"><h2>Output, yield &amp; scrap tally</h2><i>Step 3</i></div>
                <div class="panel-body">
                    <div class="rm-tiles">
                        <div class="rm-tile">
                            <div class="rm-label">Target / planned</div>
                            <input id="planned_mould_qty" type="number" min="0" name="planned_mould_qty" value="{{ old('planned_mould_qty', $defaults['planned_mould_qty']) }}">
                            @error('planned_mould_qty')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                        <div class="rm-tile">
                            <div class="rm-label">Total moulded made</div>
                            <input id="moulds_made_qty" type="number" min="0" name="moulds_made_qty" value="{{ old('moulds_made_qty', $defaults['moulds_made_qty']) }}">
                            @error('moulds_made_qty')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                        <div class="rm-tile ok">
                            <div class="rm-label">Sound accepted</div>
                            <input id="accepted_mould_qty" type="number" min="0" name="accepted_mould_qty" value="{{ old('accepted_mould_qty', $defaults['accepted_mould_qty']) }}">
                            @error('accepted_mould_qty')<div class="error-text">{{ $message }}</div>@enderror
                            <small>Ready for pour</small>
                        </div>
                        <div class="rm-tile bad">
                            <div class="rm-label">Scrap / damaged</div>
                            <input id="damaged_mould_qty" type="number" min="0" name="damaged_mould_qty" value="{{ old('damaged_mould_qty', $defaults['damaged_mould_qty']) }}">
                            @error('damaged_mould_qty')<div class="error-text">{{ $message }}</div>@enderror
                            <small>Defect rate: <b id="rm-defect-rate">—</b></small>
                        </div>
                    </div>

                    <div class="rm-tile" id="mould-yield-panel" style="margin-bottom:12px">
                        <div class="rm-yield"><span class="rm-label">Mould soundness quality yield</span><strong id="mould-yield">—</strong></div>
                        <div class="rm-bar"><div id="rm-yield-bar"></div></div>
                        <div class="muted small">Unaccounted: <b id="mould-unaccounted">0</b> · Accepted + damaged: <b id="mould-accounted">0</b> · Target threshold: not set yet</div>
                        <p id="mould-validation" role="status" aria-live="polite" style="margin:8px 0 0"></p>
                    </div>

                    <div class="rm-two">
                        <div class="field">
                            <label for="downtime_minutes">Downtime (minutes)</label>
                            <input id="downtime_minutes" type="number" min="0" name="downtime_minutes" value="{{ old('downtime_minutes', $defaults['downtime_minutes']) }}">
                            @error('downtime_minutes')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="status">Run status <span class="required">*</span></label>
                            <select id="status" name="status" required>
                                @foreach (['IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $defaults['status']) === $status)>{{ str_replace('_', ' ', $status) }}</option>
                                @endforeach
                            </select>
                            @error('status')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="field">
                        <label for="downtime_reason">Primary downtime cause / interruption</label>
                        <textarea id="downtime_reason" name="downtime_reason" maxlength="2000">{{ old('downtime_reason') }}</textarea>
                        @error('downtime_reason')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="notes">Moulder observations &amp; sand notes</label>
                        <textarea id="notes" name="notes" maxlength="5000">{{ old('notes') }}</textarea>
                        @error('notes')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>
        </div>
    </div>

    <section class="panel rm-pipe">
        <div class="panel-header rm-step"><h2>Foundry work-order routing stage pipeline</h2><span class="muted small">Step 2 of 6: batch in moulding bay</span></div>
        <div class="panel-body">
            <div class="rm-pipeline">
                <div class="rm-stage"><b>01. Cores</b><span>Not tracked here</span></div>
                <div class="rm-stage on"><b>02. Moulding</b><span id="rm-stage-moulding">0 sound / active</span></div>
                <div class="rm-stage"><b>03. Pouring</b><span>Not tracked here</span></div>
                <div class="rm-stage"><b>04. Shakeout</b><span>Not tracked here</span></div>
                <div class="rm-stage"><b>05. Shotblast</b><span>Not tracked here</span></div>
                <div class="rm-stage"><b>06. QC / FPI</b><span>Not tracked here</span></div>
            </div>
        </div>
    </section>

    <div class="rm-foot">
        <div class="rm-tally">
            <strong>Run tally snapshot:</strong>
            <span class="rm-pill" id="rm-t-made">0 total produced</span> →
            <span class="rm-pill ok" id="rm-t-ok">0 accepted</span> →
            <span class="rm-pill bad" id="rm-t-bad">0 rejected</span>
            <span class="muted" id="rm-t-down">0m downtime</span>
        </div>
        <div class="rm-actions">
            <button type="reset" class="btn">Reset form</button>
            <button type="button" class="btn" disabled title="Pouring slips are not available yet"># Generate pouring ID slip</button>
            <button type="submit" class="btn btn-primary">✓ Submit &amp; log moulding run</button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('run-form');
    const field = name => form.querySelector('[name="' + name + '"]');
    const select = document.getElementById('production_order_id');
    const summary = document.getElementById('production-order-summary');
    const revisionSelect = document.getElementById('pattern_revision_id');
    const orders = @json($orderData);
    const revisions = @json($revisionData);
    const stationSand = @json($stationSand);
    const stationSelect = document.getElementById('moulding_station_id'), sandSelect = document.getElementById('sand_system');
    let autoStation = null;
    const activeRevisions = @json($activePatternRevisionIdsByProduct);
    const productRevisions = @json($patternRevisionIdsByProduct);
    const text = (id, value) => { document.getElementById(id).textContent = value; };

    function filterPatternRevisions(order) {
        const ids = productRevisions[order ? order.productId : null];
        Array.from(revisionSelect.options).forEach(option => {
            const show = !option.value || !ids || ids.includes(option.value);
            option.hidden = !show;
            option.disabled = !show;
        });
        if (revisionSelect.selectedOptions[0]?.disabled) revisionSelect.value = '';
    }

    // The pattern's preferred station, unless one was chosen by hand; the sand is the pattern's own, else the station's default.
    function applySandFor(r) {
        const sand = (r && r.sand) || stationSand[stationSelect.value];
        if (sand && Array.from(sandSelect.options).some(o => o.value === sand)) sandSelect.value = sand;
    }
    function followPattern(r) {
        if (r && r.station && (!stationSelect.value || stationSelect.value === autoStation)) {
            stationSelect.value = r.station;
            autoStation = r.station;
        }
        applySandFor(r);
    }
    stationSelect.addEventListener('change', () => { autoStation = null; applySandFor(revisions[revisionSelect.value]); });

    function showRevision() {
        const r = revisions[revisionSelect.value];
        text('rm-pattern', r ? r.name : '—');
        text('rm-pattern-code', r ? r.code : '');
        text('rm-cavities', r && (r.cavities || r.layers) ? (r.cavities ?? '—') + ' cavities · ' + (r.layers ?? '—') + ' layers' : '—');
    }

    function updateSummary() {
        const order = orders[select.value];
        if (!order) {
            filterPatternRevisions(null);
            summary.style.display = 'none';
            text('po-summary-revision', '—');
            showRevision();
            return;
        }
        filterPatternRevisions(order);
        const ids = activeRevisions[order.productId] || [];
        if (ids.length === 1 && !revisionSelect.value) revisionSelect.value = ids[0];

        text('po-summary-number', order.number);
        text('po-summary-status', order.status);
        text('po-summary-product', order.product);
        text('po-summary-code', order.code + (order.revision !== '—' ? ' · rev ' + order.revision : ''));
        text('po-summary-customer', order.customer);
        text('po-summary-revision', order.revision);
        text('po-summary-qty', order.qty);
        text('po-summary-uom', order.uom);
        summary.style.display = 'block';
        showRevision();
        if (revisionSelect.value && !stationSelect.value) followPattern(revisions[revisionSelect.value]);
    }

    select.addEventListener('change', updateSummary);
    revisionSelect.addEventListener('change', () => { showRevision(); followPattern(revisions[revisionSelect.value]); });
    updateSummary();

    // Output tallies and the live yield bar.
    const made = field('moulds_made_qty'), accepted = field('accepted_mould_qty'), damaged = field('damaged_mould_qty');
    const downtime = field('downtime_minutes'), message = document.getElementById('mould-validation');
    const num = input => { const n = Number(input.value.trim()); return input.value.trim() !== '' && Number.isFinite(n) && n >= 0 ? n : 0; };

    function update() {
        const m = num(made), a = num(accepted), d = num(damaged), accounted = a + d, valid = accounted <= m;
        const yieldPct = m > 0 ? a / m * 100 : null;
        text('mould-yield', yieldPct === null ? '—' : yieldPct.toFixed(2) + '%');
        text('rm-defect-rate', m > 0 ? (d / m * 100).toFixed(2) + '%' : '—');
        text('mould-unaccounted', Math.max(0, m - accounted));
        text('mould-accounted', accounted);
        document.getElementById('rm-yield-bar').style.width = (yieldPct ?? 0) + '%';
        text('rm-stage-moulding', a + ' sound / active');
        text('rm-t-made', m + ' total produced');
        text('rm-t-ok', a + ' accepted' + (yieldPct === null ? '' : ' (' + yieldPct.toFixed(1) + '%)'));
        text('rm-t-bad', d + ' rejected');
        text('rm-t-down', num(downtime) + 'm downtime');
        message.textContent = valid
            ? (m === 0 ? 'Enter output quantities to calculate yield.' : 'Quantities valid. Unaccounted moulds have not been classified as accepted or damaged.')
            : 'Error: accepted moulds plus damaged moulds exceed total moulds made.';
        message.style.color = valid ? '' : '#b42318';
        [accepted, damaged].forEach(input => input.setCustomValidity(valid ? '' : 'Accepted moulds plus damaged moulds cannot exceed total moulds made.'));
        return valid;
    }
    [made, accepted, damaged, downtime].forEach(input => input.addEventListener('input', update));
    form.addEventListener('submit', function (event) {
        if (!update()) {
            event.preventDefault();
            message.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    });
    update();

    // Sound and scrap move opposite each other once total moulds made is given; sound is capped at the total.
    (function () {
        const made = form.querySelector('[name="moulds_made_qty"]'), accepted = form.querySelector('[name="accepted_mould_qty"]'), damaged = form.querySelector('[name="damaged_mould_qty"]');
        if (!made || !accepted || !damaged) { return; }
        const value = input => { const n = parseInt(input.value, 10); return Number.isFinite(n) && n >= 0 ? n : 0; };
        const totalGiven = () => made.value.trim() !== '' && value(made) > 0;
        let syncing = false;
        const balance = (changed, other) => {
            if (syncing || !totalGiven()) { return; }
            const total = value(made);
            if (changed === accepted && value(accepted) > total) { accepted.value = total; }
            if (changed === damaged && value(damaged) > total) { damaged.value = total; }
            other.value = total - value(changed);
            syncing = true; other.dispatchEvent(new Event('input')); syncing = false;
        };
        accepted.addEventListener('input', () => balance(accepted, damaged));
        damaged.addEventListener('input', () => balance(damaged, accepted));
        made.addEventListener('input', () => { if (totalGiven()) { accepted.max = value(made); damaged.max = value(made); } else { accepted.removeAttribute('max'); damaged.removeAttribute('max'); } });
    })();

    // Started / completed quick-set buttons and the elapsed time.
    const started = field('started_at'), completed = field('completed_at');
    const local = date => new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    function elapsed() {
        const a = Date.parse(started.value), b = Date.parse(completed.value);
        if (!a || !b || b < a) return text('rm-elapsed', '—');
        const mins = Math.round((b - a) / 60000);
        text('rm-elapsed', Math.floor(mins / 60) + ' hrs ' + (mins % 60) + ' mins (' + mins + ' min)');
    }
    form.querySelectorAll('[data-set]').forEach(button => button.addEventListener('click', function () {
        const target = field(button.dataset.set), day = field('run_date').value || local(new Date()).slice(0, 10);
        target.value = button.dataset.time === 'now' ? local(new Date()) : day + 'T' + button.dataset.time;
        elapsed();
    }));
    [started, completed].forEach(input => input.addEventListener('input', elapsed));
    elapsed();

    field('shift').addEventListener('change', event => text('rm-shift-chip', (event.target.value || 'No shift').toUpperCase()));
    form.addEventListener('reset', () => setTimeout(() => { updateSummary(); update(); elapsed(); }, 0));
    document.getElementById('rm-draft').addEventListener('click', function () {
        field('status').value = 'IN_PROGRESS';
        form.requestSubmit();
    });
});
</script>
@endsection
