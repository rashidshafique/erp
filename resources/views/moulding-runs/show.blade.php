@extends('layouts.app')

@section('title', $run->run_number)
@section('breadcrumb')
    <a href="{{ route('moulding-runs.index') }}">Moulding runs</a>
    / {{ $run->run_number }}
@endsection

@section('content')
    <div class="page-heading">
        <div>
            <h1>{{ $run->run_number }}</h1>
            <p>
                Run recorded for {{ $run->run_date?->format('d F Y') ?? 'an unspecified date' }}.
                Review the production order, output and downtime below.
            </p>
        </div>
        <div class="actions">
            <a href="{{ route('moulding-runs.index') }}" class="btn">Back to runs</a>
            <a href="{{ route('moulding-runs.create') }}" class="btn btn-primary">
                + New run
            </a>
        </div>
    </div>

    <div class="run-show-split">
    <div data-tab-host>
    <div class="metrics">
        <div class="metric">
            <div class="metric-label">Moulds made</div>
            <div class="metric-value">{{ \App\Support\Formats::number($run->moulds_made_qty) }}</div>
            <div class="metric-note">Recorded total output</div>
        </div>
        <div class="metric">
            <div class="metric-label">Accepted moulds</div>
            <div class="metric-value">{{ \App\Support\Formats::number($run->accepted_mould_qty) }}</div>
            <div class="metric-note">Accepted at moulding stage</div>
        </div>
        <div class="metric">
            <div class="metric-label">Damaged moulds</div>
            <div class="metric-value">{{ \App\Support\Formats::number($run->damaged_mould_qty) }}</div>
            <div class="metric-note">Moulding damage only</div>
        </div>
        <div class="metric">
            <div class="metric-label">Downtime</div>
            <div class="metric-value">{{ \App\Support\Formats::number($run->downtime_minutes) }}</div>
            <div class="metric-note">Minutes recorded</div>
        </div>
    </div>

<div class="tab-marker" data-tab-start="Run"></div>
    <section class="panel">
        <div class="panel-header">
            <h2>Run information</h2>
            <span class="status status-{{ strtolower(str_replace('_', '-', $run->status)) }}">
                {{ str_replace('_', ' ', $run->status) }}
            </span>
        </div>

        <div class="panel-body">
            <div class="detail-grid">
                <div>
                    <div class="detail-label">Production order</div>
                    <div class="detail-value">
                        {{ $run->productionOrder?->production_order_number ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="detail-label">Product</div>
                    <div class="detail-value">
                        {{ $run->productionOrder?->product?->name ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="detail-label">Order status</div>
                    <div class="detail-value">
                        {{ $run->productionOrder?->status ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="detail-label">Pattern</div>
                    <div class="detail-value">
                        {{ $run->patternRevision?->pattern?->name ?? 'Not specified' }}
                    </div>
                </div>

                <div>
                    <div class="detail-label">Pattern revision</div>
                    <div class="detail-value">
                        {{ $run->patternRevision?->shortCode() ?? '—' }}
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

                <div>
                    <div class="detail-label">Mould configuration</div>
                    <div class="detail-value">
                        {{ $run->mouldConfiguration?->name ?? 'Not specified' }}
                    </div>
                </div>

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

                <div>
                    <div class="detail-label">Planned moulds</div>
                    <div class="detail-value">
                        {{ \App\Support\Formats::number($run->planned_mould_qty ?? 0) }}
                    </div>
                </div>

                <div>
                    <div class="detail-label">Started at</div>
                    <div class="detail-value">
                        {{ $run->started_at?->displayDateTime() ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="detail-label">Completed at</div>
                    <div class="detail-value">
                        {{ $run->completed_at?->displayDateTime() ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="detail-label">Record created</div>
                    <div class="detail-value">
                        {{ $run->created_at?->displayDateTime() ?? '—' }}
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="panel" id="crew">
        <div class="panel-header"><h2>Team and sand</h2><span class="muted small">They come from the station when the run is entered; correct them here.</span></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('moulding-runs.crew', $run) }}" class="form-grid">
                @csrf @method('PUT')
                <div class="field"><label for="crew-team">Team</label><select id="crew-team" name="moulding_team_id"><option value="">— none —</option>@foreach ($teams as $t)<option value="{{ $t->id }}" @selected(old('moulding_team_id', $run->moulding_team_id) === $t->id)>{{ $t->code }} — {{ $t->name }}</option>@endforeach</select></div>
                <div class="field"><label for="crew-sand">Sand system</label><select id="crew-sand" name="sand_system"><option value="">— none —</option>@foreach ($sands as $sd)<option @selected(old('sand_system', $run->sand_system) === $sd)>{{ $sd }}</option>@endforeach</select></div>
                <div class="field" style="align-self:end"><button class="btn btn-primary" type="submit">Save team and sand</button></div>
            </form>
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

<div class="tab-marker" data-tab-start="Moulds"></div>

    <section class="panel" id="assemblies">
        <div class="panel-header">
            <h2>Mould assemblies</h2>
            <span class="muted small">{{ $run->mouldAssemblies->count() }} of {{ $run->accepted_mould_qty }} accepted moulds assembled</span>
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
                                @if ($a->is_flaskless)<span class="muted">Flaskless</span>
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
                    @if ($flaskInfo['flaskless'])Flaskless moulds: no flasks are used.
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
<div class="tab-marker" data-tab-start="Sand"></div>
@include('moulding-runs._sand', ['run' => $run])
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'moulding_run', 'id' => $run->id])
@include('attachments._panel', ['type' => 'moulding_run', 'id' => $run->id])
@include('audit._history', ['type' => 'MouldingRun', 'id' => $run->id])
    </div>
    <aside>
    @if (! in_array($run->status, ['COMPLETED', 'CANCELLED']))
    <section class="panel">
        <div class="panel-header"><h2>Record output</h2></div>
        <div class="panel-body">
            @if ($errors->has('output'))<div class="alert alert-error">{{ $errors->first('output') }}</div>@endif
            <form method="POST" action="{{ route('moulding-runs.output', $run) }}">@csrf
                <div class="form-grid">
                    <div class="field"><label>Moulds made</label><input type="number" name="moulds_made_qty" min="0" value="{{ old('moulds_made_qty', $run->moulds_made_qty) }}" required></div>
                    <div class="field"><label>Accepted</label><input type="number" name="accepted_mould_qty" min="0" value="{{ old('accepted_mould_qty', $run->accepted_mould_qty) }}" required></div>
                    <div class="field"><label>Damaged</label><input type="number" name="damaged_mould_qty" min="0" value="{{ old('damaged_mould_qty', $run->damaged_mould_qty) }}" required></div>
                    <div class="field"><label>Downtime (min)</label><input type="number" name="downtime_minutes" min="0" value="{{ old('downtime_minutes', $run->downtime_minutes) }}"></div>
                    <div class="field"><label>Downtime reason</label><input name="downtime_reason" value="{{ old('downtime_reason', $run->downtime_reason) }}"></div>
                </div>
                <button class="btn" type="submit">Save output</button>
                <button class="btn btn-primary" type="submit" name="complete" value="1">Save and complete run</button>
            </form>
        </div>
    </section>
    @endif
    @if (in_array($run->status, ['IN_PROGRESS', 'ON_HOLD'], true))
    <section class="panel">
        <div class="panel-header"><h2>Run status</h2></div>
        <div class="panel-body">
            <div class="actions">
                @if ($run->status === 'IN_PROGRESS')
                    <form method="POST" action="{{ route('moulding-runs.status', $run) }}">@csrf<input type="hidden" name="status" value="ON_HOLD"><button class="btn" type="submit">Put on hold</button></form>
                @else
                    <form method="POST" action="{{ route('moulding-runs.status', $run) }}">@csrf<input type="hidden" name="status" value="IN_PROGRESS"><button class="btn" type="submit">Resume run</button></form>
                @endif
                <form method="POST" action="{{ route('moulding-runs.status', $run) }}" onsubmit="return confirm('Cancel this run? This cannot be undone.')">@csrf<input type="hidden" name="status" value="CANCELLED">@if ($run->moulding_work_list_item_id)<label class="small" style="display:block;margin-bottom:6px"><input type="checkbox" name="skip_work_list_item" value="1"> Also skip its work list item (closes the list if nothing else is open)</label>@endif<button class="btn" type="submit">Cancel run</button></form>
            </div>
        </div>
    </section>
    @endif
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
        .run-show-split { display: grid; grid-template-columns: minmax(0, 65fr) minmax(0, 35fr); gap: 18px; align-items: start; }
        @media (max-width: 900px) { .run-show-split { grid-template-columns: 1fr; } }
    </style>
@endsection
