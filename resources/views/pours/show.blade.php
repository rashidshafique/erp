
@extends('layouts.app')

@section('title', $pour->pour_number)

@section('content')
<div class="page-heading">
    <div>
        <h1>{{ $pour->pour_number }}</h1>
        <p>
            Heat {{ $pour->heat?->heat_number ?? '—' }}
            · {{ $pour->heat?->furnace?->name ?? 'Furnace unavailable' }}
        </p>
    </div>
    <div class="actions">
        @if ($pour->heat)<a class="btn" href="{{ route('heats.show', $pour->heat) }}">Go to heat</a>@endif
        <a class="btn" href="{{ route('pours.index') }}">Back to pours</a>
    </div>
</div>

@if(session('success'))
    <div class="panel">
        <div class="panel-body" style="color:var(--success)">
            {{ session('success') }}
        </div>
    </div>
@endif

<div class="metrics">
    <div class="metric">
        <div class="metric-label">Status</div>
        <div class="metric-value" style="font-size:21px">
            {{ str_replace('_', ' ', $pour->status) }}
        </div>
        <div class="metric-note">Pour lifecycle</div>
    </div>
    <div class="metric">
        <div class="metric-label">Mould assemblies</div>
        <div class="metric-value">{{ $pour->mouldAssemblies->count() }}</div>
        <div class="metric-note">Assigned to this pour</div>
    </div>
    <div class="metric">
        <div class="metric-label">Poured weight</div>
        <div class="metric-value" style="font-size:23px">
            {{ $pour->total_poured_weight_kg !== null
                ? \App\Support\Formats::number((float) $pour->total_poured_weight_kg, 1) . ' kg'
                : '—' }}
        </div>
        <div class="metric-note">Recorded total</div>
    </div>
    <div class="metric">
        <div class="metric-label">Pour temperature</div>
        <div class="metric-value" style="font-size:23px">
            {{ $pour->pour_temperature_c !== null
                ? \App\Support\Formats::number((float) $pour->pour_temperature_c, 0) . ' °C'
                : '—' }}
        </div>
        <div class="metric-note">Recorded temperature</div>
    </div>
</div>

<div class="tab-marker" data-tab-start="Overview"></div>
@include('pours._gate', ['pour' => $pour])

<div class="panel">
    <div class="panel-header">
        <h2>Pour information</h2>
    </div>
    <div class="panel-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px">
            <div>
                <div class="muted small">Heat number</div>
                <x-heat-light :heat="$pour->heat" /><strong>{{ $pour->heat?->heat_number ?? '—' }}</strong>
            </div>
            <div>
                <div class="muted small">Furnace</div>
                <strong>{{ $pour->heat?->furnace?->code ?? '—' }}</strong>
                <div class="muted small">{{ $pour->heat?->furnace?->name ?? '' }}</div>
            </div>
            <div>
                <div class="muted small">Planned time</div>
                <strong>{{ $pour->planned_at?->displayDateTime() ?? '—' }}</strong>
            </div>
            <div>
                <div class="muted small">Started</div>
                <strong>{{ $pour->started_at?->displayDateTime() ?? '—' }}</strong>
            </div>
            <div>
                <div class="muted small">Completed</div>
                <strong>{{ $pour->completed_at?->displayDateTime() ?? '—' }}</strong>
            </div>
            <div>
                <div class="muted small">Operator</div>
                <strong>{{ $pour->operator_name ?: '—' }}</strong>
            </div>
            <div>
                <div class="muted small">Ladle</div>
                <strong>{{ $pour->ladle_reference ?: '—' }}</strong>
            </div>
        </div>

        @if($pour->notes)
            <div style="margin-top:22px">
                <div class="muted small">Notes</div>
                <p>{{ $pour->notes }}</p>
            </div>
        @endif
    </div>
</div>

<div class="tab-marker" data-tab-start="Moulds"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Mould assemblies assigned to this pour</h2>
        <span class="muted small">{{ $pour->mouldAssemblies->count() }} assemblies</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Assembly</th>
                    <th>Configuration</th>
                    <th>Production order</th>
                    <th>Castings poured</th>
                    <th>Poured weight</th>
                    <th>Casting lots</th>
                    <th title="Cover (jacket) and weights put on the mould for pouring; optional">Cover · weights</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($pour->mouldAssemblies as $assignment)
                    <tr>
                        <td>
                            {{ $assignment->mouldAssembly?->assembly_number ?? '—' }}
                        </td>
                        <td>
                            {{ $assignment->mouldAssembly?->configuration?->name ?? '—' }}
                        </td>
                        <td>
                            {{ $assignment->productionOrder?->production_order_number ?? '—' }}
                            @if($assignment->productionOrder?->product)
                                <x-product-cell :product="$assignment->productionOrder->product" />
                            @endif
                        </td>
                        <td>{{ \App\Support\Formats::number((int) $assignment->castings_poured_qty) }}</td>
                        <td>
                            {{ $assignment->poured_weight_kg !== null
                                ? \App\Support\Formats::number((float) $assignment->poured_weight_kg, 3) . ' kg'
                                : '—' }}
                        </td>
                        <td>@foreach ($assignment->castingLots as $lot)<a href="{{ route('lots.show', $lot) }}">{{ $lot->label }}</a><div class="muted small">{{ $lot->product?->code }} × {{ $lot->quantity_poured }}</div>@endforeach @if ($assignment->castingLots->isEmpty())—@endif</td>
                        <td><form method="POST" action="{{ route('pours.assemblies.cover', [$pour, $assignment]) }}" style="display:flex;gap:4px;align-items:center">@csrf @method('PUT')
                            <input name="cover_reference" value="{{ $assignment->cover_reference }}" maxlength="40" placeholder="cover" style="width:80px;min-height:24px;padding:1px 6px;font-size:12px" aria-label="Cover used on {{ $assignment->mouldAssembly?->assembly_number }}">
                            <input type="number" name="weights_count" value="{{ $assignment->weights_count }}" min="0" max="100" placeholder="wts" style="width:56px;min-height:24px;padding:1px 6px;font-size:12px" aria-label="Weights on {{ $assignment->mouldAssembly?->assembly_number }}">
                            <button class="btn btn-small" type="submit">Save</button></form>
                            @if ($assignment->mouldAssembly?->is_flaskless)<div class="muted small">flaskless</div>
                            @elseif ($assignment->mouldAssembly)
                                @php [$flaskDefault, $flaskFrom] = app(\App\Services\Production\FlaskManager::class)->mouldDefault($assignment->mouldAssembly); @endphp
                                <div style="margin-top:4px">@if ($pour->status !== 'COMPLETED')@include('flasks._freed-select', ['action' => route('pours.assemblies.flasks', [$pour, $assignment]), 'value' => $assignment->mouldAssembly->flasks_freed, 'default' => $flaskDefault, 'from' => $flaskFrom, 'label' => 'Flasks freed for '.$assignment->mouldAssembly->assembly_number])
                                @else<span class="muted small">Flasks freed {{ strtolower(\App\Services\Production\FlaskManager::freedLabel($assignment->mouldAssembly->flasks_freed ?? $flaskDefault)) }}</span>@endif</div>
                            @endif</td>
                        <td>@if ($pour->status !== 'COMPLETED')<form method="POST" action="{{ route('pours.assemblies.detach', [$pour, $assignment]) }}" onsubmit="return confirm('Remove this mould from the pour?')">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove'])</form>@endif</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted" style="padding:30px;text-align:center">
                            No mould assemblies have been assigned yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="panel-body">
        @error('assemblies')<div class="alert alert-error">{{ $message }}</div>@enderror
        @if ($candidates->isNotEmpty())
            <form method="POST" action="{{ route('pours.assemblies.attach', $pour) }}">@csrf
                <div class="field"><label>Add moulds to this pour</label>
                    <label style="display:block;font-weight:600;padding:3px 0 6px"><input type="checkbox" id="pour-assembly-select-all"> Select all ({{ $candidates->count() }})</label>
                    <div style="max-height:220px;overflow:auto;border:1px solid var(--border);border-radius:6px;padding:8px">
                        @foreach ($candidates as $c)
                            <label style="display:block;font-weight:400;padding:3px 0"><input type="checkbox" name="assembly_ids[]" value="{{ $c->id }}" class="pour-assembly-checkbox">
                                {{ $c->assembly_number }} — {{ $c->mouldingRun?->productionOrder?->production_order_number }} · {{ $c->mouldingRun?->productionOrder?->product?->code }}</label>
                        @endforeach
                    </div>
                    <script>
                    (function () {
                        var all = document.getElementById('pour-assembly-select-all');
                        var boxes = document.querySelectorAll('.pour-assembly-checkbox');
                        all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); });
                        boxes.forEach(function (b) { b.addEventListener('change', function () { all.checked = [].every.call(boxes, function (x) { return x.checked; }); }); });
                    })();
                    </script>
                    <span class="muted small">Only moulds from released production orders are listed. Each mould is checked against the heat's iron grade.</span></div>
                <button class="btn btn-primary" type="submit">Add selected moulds</button>
            </form>
        @elseif (in_array($pour->status, ['PLANNED', 'IN_PROGRESS'], true))
            <p class="muted small">No unpoured moulds from released orders. Assemble moulds on a moulding run first.</p>
        @endif
    </div>
</div>
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'pour', 'id' => $pour->id])
@include('attachments._panel', ['type' => 'pour', 'id' => $pour->id])
@include('audit._history', ['type' => 'Pour', 'id' => $pour->id])
@endsection
