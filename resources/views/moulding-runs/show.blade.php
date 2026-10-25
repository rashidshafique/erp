@extends('layouts.app')

@section('title', $run->run_number)
@section('breadcrumb')
    <a href="{{ route('moulding-runs.index') }}">Moulding runs</a>
    / {{ $run->run_number }}
@endsection

@section('content')
    <div class="page-heading">
        <div>
            <h1 style="display:flex;align-items:center;gap:12px;flex-wrap:wrap"><span class="code">{{ $run->run_number }}</span>
                <span class="status status-{{ strtolower(str_replace('_', '-', $run->status)) }}" style="font-size:12px">{{ str_replace('_', ' ', $run->status) }}</span>@if ($note = $run->planningNote())<span class="muted small" title="Not started from an issued work list">{{ $note }}</span>@endif</h1>
            <div class="run-meta">
                <span class="run-meta-date"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg> {{ $run->run_date?->displayDate() ?? __('No date') }}</span>
                <span class="run-meta-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> {{ __('Started') }}:
                    <details style="display:inline-block;position:relative">
                        <summary style="cursor:pointer;display:inline;text-decoration:underline dotted" title="Click to edit">{{ $run->started_at?->displayDateTime() ?? '—' }}</summary>
                        <form method="POST" action="{{ route('moulding-runs.started-at', $run) }}" style="position:absolute;z-index:10;left:0;top:100%;margin-top:6px;padding:10px;background:#fff;border:1px solid #ddd;border-radius:6px;display:flex;gap:8px;white-space:nowrap">
                            @csrf @method('PUT')
                            <input type="datetime-local" name="started_at" value="{{ old('started_at', $run->started_at?->format('Y-m-d\TH:i')) }}" required>
                            <button class="btn btn-primary" type="submit">Save</button>
                        </form>
                    </details>
                </span>
                @if ($run->completed_at)
                    <span class="run-meta-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg> {{ __('Completed') }}: {{ $run->completed_at->displayDateTime() }}</span>
                @endif
            </div>
        </div>
        <div class="actions">
            <a href="{{ route('moulding-runs.index') }}" class="btn">Back to runs</a>
            <a href="{{ route('moulding-runs.create') }}" class="btn btn-primary">
                + New run
            </a>
        </div>
    </div>

    @php
        $expected = $perMould !== null ? rtrim(rtrim(\App\Support\Formats::number($perMould * ($run->planned_mould_qty ?? 0), 1), '0'), '.') : null;
        $expectedAccepted = $perMould !== null ? rtrim(rtrim(\App\Support\Formats::number($perMould * ($run->accepted_mould_qty ?? 0), 1), '0'), '.') : null;
        $acceptedVsPlanned = ($perMould !== null && ($run->planned_mould_qty ?? 0) > 0) ? $run->accepted_mould_qty / $run->planned_mould_qty : null;
    @endphp
    <div class="run-kpis">
        <div class="run-kpi"><div class="run-kpi-label">Planned / target</div><div class="run-kpi-value">{{ \App\Support\Formats::number($run->planned_mould_qty ?? 0) }} moulds <span class="small" style="display:block;font-weight:400" @if ($expected === null) title="The pattern has no cavity map" @endif>{{ $expected ?? '—' }} castings</span></div></div>
        <div class="run-kpi"><div class="run-kpi-label">Moulds made</div><div class="run-kpi-value">{{ \App\Support\Formats::number($run->moulds_made_qty) }}</div></div>
        <div class="run-kpi"><div class="run-kpi-label">Accepted</div><div class="run-kpi-value">{{ \App\Support\Formats::number($run->accepted_mould_qty) }} <span class="small" style="display:block;font-weight:400" @if ($expectedAccepted === null) title="The pattern has no cavity map" @endif>{{ $expectedAccepted ?? '—' }} castings @if ($acceptedVsPlanned !== null) · <span style="{{ $acceptedVsPlanned > 0.9 ? 'color:var(--success)' : '' }}">{{ \App\Support\Formats::number($acceptedVsPlanned * 100, 0) }}% of planned</span> @endif</span></div></div>
        <div class="run-kpi"><div class="run-kpi-label">Damaged</div><div class="run-kpi-value">{{ \App\Support\Formats::number($run->damaged_mould_qty) }}@php $remake = $run->status === 'COMPLETED' ? max(0, (int) $run->planned_mould_qty - (int) $run->accepted_mould_qty) : 0; @endphp @if ($remake > 0)<span class="small" style="display:block;font-weight:400;color:var(--danger,#b42318)">Remake {{ $remake }} moulds @if ($perMould !== null)(≈ {{ rtrim(rtrim(\App\Support\Formats::number($perMould * $remake, 1), '0'), '.') }} castings) @endif</span>@endif</div></div>
        <div class="run-kpi"><div class="run-kpi-label">Downtime</div><div class="run-kpi-value">{{ \App\Support\Formats::number($run->downtime_minutes) }} min</div></div>
        <span class="pill pill-accent" style="align-self:center;margin:0 8px 0 auto">Live metrics</span>
    </div>
    <div class="run-show-split">
    <div data-tab-host>

<div class="tab-marker" data-tab-start="Run"></div>
    <section class="panel">
        <div class="panel-header">
            <h2>Run information</h2>
            <span class="status status-{{ strtolower(str_replace('_', '-', $run->status)) }}">
                {{ str_replace('_', ' ', $run->status) }}
            </span>
        </div>

        <div class="panel-body">
            <div class="detail-label" style="margin:0 0 8px;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Production order and product</div>
            <div class="detail-grid">
                <div>
                    <div class="detail-label">Production order</div>
                    <div class="detail-value">
                        <span class="code">{{ $run->productionOrder?->production_order_number ?? '—' }}</span>
                    </div>
                </div>

                <div>
                    <div class="detail-label">Order status</div>
                    <div class="detail-value">
                        {{ $run->productionOrder?->status ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="detail-label">Planned moulds</div>
                    <div class="detail-value">
                        {{ \App\Support\Formats::number($run->planned_mould_qty ?? 0) }}
                    </div>
                </div>

                <div>
                    <div class="detail-label">Product</div>
                    <div class="detail-value">
                        @if ($run->productionOrder?->product)<x-product-cell :product="$run->productionOrder->product" :revision="$run->productionOrder->productRevision?->revision" />@else—@endif
                    </div>
                </div>
            </div>
            <div style="height:20px"></div>
            <div class="detail-label" style="margin:0 0 8px;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Pattern</div>
            <div class="detail-grid">
                <div>
                    <div class="detail-label">Pattern</div>
                    <div class="detail-value">
                        @if ($run->patternRevision)<x-item :name="$run->patternRevision->pattern?->name" :code="$run->patternRevision->pattern?->code" />@else Not specified @endif
                    </div>
                </div>

                <div>
                    <div class="detail-label">Pattern revision</div>
                    <div class="detail-value">
                        <span class="code">{{ $run->patternRevision?->shortCode() ?? '—' }}</span>
                    </div>
                </div>

                <div>
                    <div class="detail-label">Mould configuration</div>
                    <div class="detail-value">
                        {{ $run->mouldConfiguration?->name ?? 'Not specified' }}
                    </div>
                </div>

                @if ($perMould !== null)
                <div>
                    <div class="detail-label">Expected products per mould (cavity map)</div>
                    <div class="detail-value">
                        {{ rtrim(rtrim(\App\Support\Formats::number($perMould, 3), '0'), '.') }}
                        &middot; accepted moulds {{ $run->accepted_mould_qty }}
                        &rarr; about {{ rtrim(rtrim(\App\Support\Formats::number($perMould * $run->accepted_mould_qty, 1), '0'), '.') }} products
                    </div>
                </div>
                @endif
            </div>
            <div style="height:20px"></div>
            <div class="detail-label" style="margin:0 0 8px;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Run</div>
            <div class="detail-grid">
                <div>
                    <div class="detail-label">Shift</div>
                    <div class="detail-value">{{ $run->shift ?: '—' }}</div>
                </div>

                <div>
                    <div class="detail-label">Moulding method</div>
                    <div class="detail-value">{{ $run->moulding_method ?: '—' }}</div>
                </div>

                <div>
                    <div class="detail-label">Sand system</div>
                    <div class="detail-value">{{ $run->sand_system ?: '—' }}</div>
                </div>

                <div>
                    <div class="detail-label">Operator</div>
                    <div class="detail-value">{{ $run->operator_name ?: '—' }}</div>
                </div>

                <div>
                    <div class="detail-label">Team</div>
                    <div class="detail-value">{{ $run->team_name ?: '—' }}</div>
                </div>

                <div>
                    <div class="detail-label">Station</div>
                    <div class="detail-value">{{ $run->station?->code ?? '—' }}@if($run->workListItem) · <a href="{{ route('work-lists.show', $run->workListItem->moulding_work_list_id) }}">work list</a>@endif</div>
                </div>

                <!-- <div>
                    <div class="detail-label">Started at</div>
                    <div class="detail-value">
                        {{ $run->started_at?->displayDateTime() ?? '—' }}
                    </div>
                </div> -->

                <!-- <div>
                    <div class="detail-label">Completed at</div>
                    <div class="detail-value">
                        {{ $run->completed_at?->displayDateTime() ?? '—' }}
                    </div>
                </div> -->

                <div>
                    <div class="detail-label">Record created</div>
                    <div class="detail-value">
                        {{ $run->created_at?->displayDateTime() ?? '—' }}
                    </div>
                </div>
            </div>
        </div>
    </section>

    @php
        $reading = function (string $key) use ($sandReadings) {
            $m = $sandReadings->get($key);
            if (! $m || $m->value === null) {
                return ['—', null];
            }
            $range = $m->min_value !== null || $m->max_value !== null ? 'Opt: '.rtrim(rtrim(number_format((float) $m->min_value, 2, '.', ''), '0'), '.').'–'.rtrim(rtrim(number_format((float) $m->max_value, 2, '.', ''), '0'), '.').($m->unit ? ' '.$m->unit : '') : null;

            return [rtrim(rtrim(number_format((float) $m->value, 2, '.', ''), '0'), '.').($m->unit ? ' '.$m->unit : ''), $range];
        };
    @endphp
    <section class="panel">
        <div class="panel-header">
            <h2>Sand loop readings</h2>
            <span class="muted small">{{ $sandSet ? 'From the sand test of '.$sandSet->taken_at->displayShortDateTime() : 'No sand test yet' }} · <a href="{{ route('sand-tests.index', ['moulding_run_id' => $run->id]) }}">Sand lab</a></span>
        </div>
        <div class="panel-body">
            <div class="run-sand-grid">
                @foreach ([['moisture_pct', 'Moisture content'], ['green_strength_kpa', 'Green comp. strength'], ['sand_temperature_c', 'Sand temperature']] as [$key, $title])
                    @php([$text, $range] = $reading($key))
                    <div class="run-sand-tile"><div class="rm-lbl">{{ $title }}</div><strong class="code">{{ $text }}</strong>@if ($range)<span class="code muted small"> ({{ $range }})</span>@endif</div>
                @endforeach
                @php($hardness = \App\Models\MeasurementSet::with('measurements')->where('kind', 'MOULD')->where('moulding_run_id', $run->id)->orderByDesc('taken_at')->first()?->measurements->firstWhere('characteristic_key', 'hardness_b_avg'))
                <div class="run-sand-tile"><div class="rm-lbl">Mould hardness (B, avg)</div><strong class="code">{{ $hardness ? rtrim(rtrim(number_format((float) $hardness->value, 2, '.', ''), '0'), '.') : '—' }}</strong></div>
            </div>
        </div>
    </section>


    <section class="panel">
        <div class="panel-header">
            <h2>Downtime and notes</h2>
        </div>

        <div class="panel-body">
            <div style="margin-bottom:20px">
                <div class="detail-label">Downtime reason</div>
                <div style="white-space:pre-wrap">{{ $run->downtime_reason ?: 'No downtime reason recorded.' }}</div>
            </div>

            <div>
                <div class="detail-label">Notes</div>
                <div style="white-space:pre-wrap">{{ $run->notes ?: 'No additional notes.' }}</div>
            </div>
        </div>
    </section>

@foreach (app(\App\Domains\Core\ModuleRegistry::class)->panels('moulding-run.show') as $panel)@include($panel, ['run' => $run])@endforeach
<div class="tab-marker" data-tab-start="Moulds"></div>

    <section class="panel" id="assemblies">
        <div class="panel-header">
            <h2>Mould assemblies</h2>
            <span class="muted small">{{ $run->mouldAssemblies->count() }} of {{ $run->accepted_mould_qty }} accepted moulds assembled @if ($flaskInfo['flaskless'])· flaskless moulds are assembled at the station, so this is automatic @endif</span>
        </div>
        <div class="panel-body">
            <p class="muted small">Each assembly is one finished mould that will be poured. Its cavities are copied from the pattern's cavity map, so every cavity already knows which product (or part of a product) it makes.</p>
            @if ($run->mouldAssemblies->isNotEmpty())
                <div class="table-wrap"><table>
                    <thead><tr><th>Assembly</th><th>Status</th><th>Assembled</th><th>Pour</th><th>Flasks</th></tr></thead>
                    <tbody>
                    @foreach ($run->mouldAssemblies as $a)
                        <tr><td>{{ $a->assembly_number }}</td><td>{{ str_replace('_', ' ', $a->status) }}</td>
                            <td>{{ $a->assembled_at?->displayShortDateTime() }}<div class="muted small">{{ $a->assembled_by }}</div></td>
                            <td>@if ($a->pourAssignment?->pour)<a href="{{ route('pours.show', $a->pourAssignment->pour) }}">{{ $a->pourAssignment->pour->pour_number }}</a>@else<span class="muted">not poured</span>@endif</td>
                            <td class="small">
                                @if ($a->is_flaskless)<span class="muted">Flaskless</span>@if ($a->flaskSize) <span class="pill">{{ $a->flaskSize->name }}</span>@endif
                                @else
                                    @foreach ($a->layers as $layer)
                                        <div style="display:flex;gap:6px;align-items:center;margin-bottom:2px">
                                            <span style="width:48px" class="muted">{{ ucfirst(strtolower($layer->layer_type)) }}</span>
                                            @if ($layer->flask)<a class="mono" href="{{ route('flasks.show', $layer->flask) }}">{{ $layer->flask->label() }}</a>@else<span class="muted">none</span>@endif
                                            @if (in_array($a->status, ['PREPARED', 'READY'], true) || ! $a->pourAssignment)
                                                <form method="POST" action="{{ route('moulding-runs.layers.flask', [$run, $layer]) }}" style="display:flex;gap:4px">@csrf @method('PUT')<input name="flask" maxlength="40" placeholder="scan / type" style="width:96px;min-height:24px;padding:1px 6px;font-size:12px" aria-label="Flask used as the {{ strtolower($layer->layer_type) }} of {{ $a->assembly_number }}"><button class="btn btn-small" type="submit">Swap</button></form>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if ($a->flasks()->exists())<form method="POST" action="{{ route('moulding-runs.assemblies.release', [$run, $a]) }}" onsubmit="return confirm('Release the flasks of {{ $a->assembly_number }}? Use this when it was knocked out without a shakeout record.')">@csrf<button class="btn btn-small" type="submit">Release flasks</button></form>@endif
                                @endif
                            </td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif

            @if ($run->status !== 'CANCELLED' && $run->accepted_mould_qty > $run->mouldAssemblies->count())
                <h3 style="margin-top:20px;font-size:15px">Assemble moulds</h3>
                <p class="muted small" style="margin:0 0 8px">
                    @if ($flaskInfo['flaskless'])Flaskless moulds: no flasks are used.@if ($flaskInfo['sizes']->isNotEmpty()) Block size: {{ $flaskInfo['sizes']->pluck('name')->implode(' or ') }}.@else No block size is set for this pattern, so the moulds' size is not recorded.@endif
                    @elseif ($flaskInfo['sizes']->isEmpty())No flask size is set for this pattern{{ $run->station ? ' that station '.$run->station->code.' handles' : '' }}, so moulds are made without flasks.
                    @else Flasks: {{ $flaskInfo['sizes']->pluck('name')->implode(' or ') }} — {{ $flaskInfo['free'] }} of {{ $flaskInfo['open'] }} {{ $flaskInfo['open'] === 1 ? 'set' : 'sets' }} free. <a href="{{ route('flasks.index') }}">Flask board</a>@endif
                </p>
                <form method="POST" action="{{ route('moulding-runs.assemble', $run) }}">@csrf
                    <div class="form-grid">
                        <div class="field"><label for="count">How many moulds *</label><input id="count" name="count" type="number" min="1" max="{{ $run->accepted_mould_qty - $run->mouldAssemblies->count() }}" value="{{ old('count', $run->accepted_mould_qty - $run->mouldAssemblies->count()) }}" required></div>
                        <div class="field"><label for="mould_configuration_id">Mould configuration *</label>
                            <select id="mould_configuration_id" name="mould_configuration_id" required>
                                @foreach ($configurations as $c)<option value="{{ $c->id }}" @selected(old('mould_configuration_id', $run->mould_configuration_id) === $c->id)>{{ $c->code }} — {{ $c->name }}</option>@endforeach
                            </select></div>
                        <div class="field"><label for="layers">Layers per mould *</label><input id="layers" name="layers" type="number" min="1" max="12" value="{{ old('layers', $run->mouldConfiguration?->nominal_layer_count ?? 2) }}" required><span class="muted small">2 = drag and cope. More layers are stack moulds.</span></div>
                        <div class="field"><label for="assembled_by">Assembled by</label><input id="assembled_by" name="assembled_by" maxlength="100" value="{{ old('assembled_by') }}"></div>
                        <div class="field"><label for="checked_by">Checked by</label><input id="checked_by" name="checked_by" maxlength="100" value="{{ old('checked_by') }}"></div>
                    </div>
                    <button class="btn btn-primary" type="submit">Assemble moulds</button>
                </form>
            @endif
        </div>
    </section>

    @if ($flaskInfo['held']->isNotEmpty())
        <section class="panel" id="run-flasks">
            <div class="panel-header"><h2>Flasks held by this run</h2><span class="muted small">{{ $flaskInfo['held']->count() }} flasks for the moulds made, under the "runs claim by count" rule</span></div>
            <div class="panel-body">
                <p class="small" style="margin-top:0">@foreach ($flaskInfo['held'] as $f)<a class="mono" href="{{ route('flasks.show', $f) }}">{{ $f->label() }}</a>@if (! $loop->last), @endif @endforeach</p>
                <form method="POST" action="{{ route('moulding-runs.release-flasks', $run) }}" onsubmit="return confirm('Release all flasks held by this run? Each counts one use.')">@csrf<button class="btn" type="submit">Release flasks</button></form>
            </div>
        </section>
    @endif
@include('moulding-runs._mould-hardness', ['run' => $run])
<div class="tab-marker" data-tab-start="Sand"></div>
@include('moulding-runs._sand', ['run' => $run])
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'moulding_run', 'id' => $run->id])
@include('attachments._panel', ['type' => 'moulding_run', 'id' => $run->id])
@include('audit._history', ['type' => 'MouldingRun', 'id' => $run->id])
    </div>
    <aside>
    @if (in_array($run->status, ['IN_PROGRESS', 'ON_HOLD'], true))
    <div style="margin-bottom:16px">
            <div class="actions">
                <button class="btn btn-primary" type="submit" form="record-output-form" name="complete" value="1">Save and complete run</button>
                @if ($run->status === 'IN_PROGRESS')
                    <form method="POST" action="{{ route('moulding-runs.status', $run) }}">@csrf<input type="hidden" name="status" value="ON_HOLD"><button class="btn" type="submit">Put on hold</button></form>
                @else
                    <form method="POST" action="{{ route('moulding-runs.status', $run) }}">@csrf<input type="hidden" name="status" value="IN_PROGRESS"><button class="btn" type="submit">Resume run</button></form>
                @endif
                <form method="POST" action="{{ route('moulding-runs.status', $run) }}" onsubmit="return confirm('Cancel this run? This cannot be undone.')">@csrf<input type="hidden" name="status" value="CANCELLED">@if ($run->moulding_work_list_item_id)<label class="small" style="display:block;margin-bottom:6px"><input type="checkbox" name="skip_work_list_item" value="1"> Also skip its work list item (closes the list if nothing else is open)</label>@endif<button class="btn" type="submit">Cancel run</button></form>
            </div>
    </div>
    @endif
    @if (! in_array($run->status, ['COMPLETED', 'CANCELLED']))
    <section class="panel">
        <div class="panel-header"><h2>Record output</h2></div>
        <div class="panel-body">
            @if ($errors->has('output'))<div class="alert alert-error">{{ $errors->first('output') }}</div>@endif
            <form id="record-output-form" method="POST" action="{{ route('moulding-runs.output', $run) }}">@csrf
                <div class="rm-tiles">
                    <div class="rm-tile"><div class="rm-label">Total moulds made</div><input type="number" name="moulds_made_qty" min="0" value="{{ old('moulds_made_qty', $run->moulds_made_qty) }}" required></div>
                    <div class="rm-tile ok"><div class="rm-label">Sound accepted</div><input type="number" name="accepted_mould_qty" min="0" value="{{ old('accepted_mould_qty', $run->accepted_mould_qty) }}" required><small>Ready for pour</small></div>
                    <div class="rm-tile bad"><div class="rm-label">Scrap / damaged</div><input type="number" name="damaged_mould_qty" min="0" value="{{ old('damaged_mould_qty', $run->damaged_mould_qty) }}" required></div>
                    <div class="rm-tile"><div class="rm-label">Downtime (min)</div><input type="number" name="downtime_minutes" min="0" value="{{ old('downtime_minutes', $run->downtime_minutes) }}"></div>
                </div>
                <div class="field"><label>Downtime reason</label><input name="downtime_reason" value="{{ old('downtime_reason', $run->downtime_reason) }}"></div>
                <button class="btn" type="submit">Save output</button>
                @if (! in_array($run->status, ['IN_PROGRESS', 'ON_HOLD'], true))
                <button class="btn btn-primary" type="submit" name="complete" value="1">Save and complete run</button>
                @endif
            </form>
        </div>
    </section>
    @endif
    <script>
    (function () {
        const form = document.getElementById('record-output-form');
        if (!form) { return; }
        // Sound and scrap move opposite each other once total moulds made is given; sound is capped at the total.
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
    </script>
    <section class="panel" id="crew">
        <div class="panel-header"><h2>Station, team and sand</h2><span class="muted small">The bulk editor takes team and sand from the station; correct them here. A sand test started from this run uses the station.</span></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('moulding-runs.crew', $run) }}" class="form-grid">
                @csrf @method('PUT')
                <div class="field"><label for="crew-station">Station</label><select id="crew-station" name="moulding_station_id"><option value="">— none —</option>@foreach ($stations as $st)<option value="{{ $st->id }}" @selected(old('moulding_station_id', $run->moulding_station_id) === $st->id)>{{ $st->code }} — {{ $st->name }}</option>@endforeach</select></div>
                <div class="field"><label for="crew-team">Team</label><select id="crew-team" name="moulding_team_id"><option value="">— none —</option>@foreach ($teams as $t)<option value="{{ $t->id }}" @selected(old('moulding_team_id', $run->moulding_team_id) === $t->id)>{{ $t->code }} — {{ $t->name }}</option>@endforeach</select></div>
                <div class="field"><label for="crew-sand">Sand system</label><select id="crew-sand" name="sand_system"><option value="">— none —</option>@foreach ($sands as $sd)<option @selected(old('sand_system', $run->sand_system) === $sd)>{{ $sd }}</option>@endforeach</select></div>
                <div class="field" style="align-self:end"><button class="btn btn-primary" type="submit">Save station, team and sand</button></div>
            </form>
        </div>
    </section>
    @if ($run->status === 'COMPLETED')
    <section class="panel">
        <div class="panel-header"><h2>Run completed</h2></div>
        <div class="panel-body">
            @if ($errors->has('output'))<div class="alert alert-error">{{ $errors->first('output') }}</div>@endif
            <p class="small" style="margin-top:0">Marked completed by mistake? Reopen the run to correct its output.</p>
            <form method="POST" action="{{ route('moulding-runs.reopen', $run) }}" onsubmit="return confirm('Reopen this run? It goes back to In progress.')">@csrf<button class="btn" type="submit">Reopen run</button></form>
        </div>
    </section>
    @endif

    </aside>
    </div>
    <style>
        .run-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 18px; margin-top: 8px; font-size: 14px; color: var(--muted); }
        .run-meta span { display: inline-flex; align-items: center; gap: 7px; }
        .run-meta-date { padding: 6px 12px; border-radius: 6px; background: #e8eef5; color: #1f3a5a; font-weight: 600; }
        .rm-tiles { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
        .rm-tile { padding: 10px; border-radius: 8px; background: #eaf1f8; }
        .rm-tile.ok { background: #e6f4ec; } .rm-tile.bad { background: #fbe9e7; }
        .rm-label { margin-bottom: 6px; font: 600 10.5px ui-monospace, Menlo, monospace; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
        .rm-tile small { display: block; margin-top: 4px; font-size: 11px; color: var(--muted); }
        .run-kpis { display: flex; align-items: stretch; flex-wrap: wrap; gap: 0; margin-bottom: 16px; padding: 12px 8px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
        .run-kpi { padding: 0 18px; border-left: 1px solid var(--border); }
        .run-kpi:first-child { border-left: 0; }
        .run-kpi-label { font-size: 11.5px; font-weight: 400; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); }
        .run-kpi-value { margin-top: 2px; font-size: 20px; font-weight: 600; white-space: nowrap; }
        .run-sand-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; }
        .run-sand-tile { padding: 12px; border-radius: 8px; background: #eef3f9; }
        .run-sand-tile .rm-lbl { margin-bottom: 4px; font-size: 12px; font-weight: 600; color: var(--muted); }
        .run-show-split { display: grid; grid-template-columns: minmax(0, 65fr) minmax(0, 35fr); gap: 18px; align-items: start; }
        @media (max-width: 900px) { .run-show-split { grid-template-columns: 1fr; } }
    </style>
@endsection
