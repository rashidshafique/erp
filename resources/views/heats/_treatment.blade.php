@php $trs = $heat->treatments()->get(); $types = \App\Models\HeatTreatment::TYPES; @endphp
<div class="panel">
    <div class="panel-header"><h2>Metal treatment and inoculation</h2></div>
    <div class="panel-body">
        @if ($errors->has('treatment'))<div class="alert alert-error">{{ $errors->first('treatment') }}</div>@endif
        @if ($heat->ironGrade)
            <p class="muted small">Grade {{ $heat->ironGrade->code }}:
                {{ $heat->ironGrade->needs_mg_treatment ? 'needs magnesium treatment' : 'no magnesium treatment' }},
                {{ $heat->ironGrade->needs_inoculation ? 'needs inoculation' : 'no inoculation required' }}.
                Fade limit {{ \App\Http\Controllers\ProcessControlController::maxFadeMinutes() }} min.</p>
        @endif
    </div>
    @if ($trs->isNotEmpty())
        <div class="table-wrap"><table><thead><tr><th>Time</th><th>Treatment</th><th>Material</th><th>Amount</th><th>Method</th><th>Temp</th><th>Recovery</th><th>By</th><th></th></tr></thead><tbody>
            @foreach ($trs as $t)
                <tr><td>{{ $t->treated_at->displayAt() }}</td><td>{{ $types[$t->treatment_type] }}</td><td>{{ $t->material }}</td>
                    <td>{{ $t->amount_kg }} kg @if($t->addition_pct)<div class="muted small">{{ $t->addition_pct }} %</div>@endif</td>
                    <td>{{ \App\Models\HeatTreatment::METHODS[$t->method] ?? '' }}</td><td>{{ $t->temperature_c }}</td><td>{{ $t->recovery_pct }}</td><td>{{ $t->treated_by }}</td>
                    <td><form method="POST" action="{{ route('heats.treatments.destroy', [$heat, $t]) }}">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'name' => $types[$t->treatment_type]])</form></td></tr>
            @endforeach
        </tbody></table></div>
    @endif
    <div class="panel-body" style="border-top:1px solid var(--border,#ddd)">
        <form method="POST" action="{{ route('heats.treatments.store', $heat) }}" class="form-grid">@csrf
            <label>Treatment<select name="treatment_type">@foreach ($types as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label>Time<input type="datetime-local" name="treated_at"><span class="muted small">Leave empty for now</span></label>
            <label>Material<input name="material" placeholder="FeSiMg 6%, FeSi75..."></label>
            <label>Amount (kg)<input type="number" step="any" name="amount_kg"></label>
            <label>Metal treated (kg)<input type="number" step="any" name="metal_treated_kg"><span class="muted small">Gives the addition %</span></label>
            <label>Method<select name="method"><option value="">—</option>@foreach (\App\Models\HeatTreatment::METHODS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label>Metal temperature °C<input type="number" step="any" name="temperature_c"></label>
            @include('maintenance::gauges._select', ['plain' => true, 'id' => 'treatment_gauge', 'label' => 'Pyrometer', 'types' => ['THERMOCOUPLE']])
            <label>Ladle<input name="ladle_reference"></label>
            <label>Mg recovery %<input type="number" step="any" name="recovery_pct"></label>
            <label>Treated by<input name="treated_by"></label>
            <div><button class="btn btn-primary">Record treatment</button></div>
        </form>
    </div>
</div>
