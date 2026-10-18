@extends('layouts.app')

@section('title', $grade->code)
@section('breadcrumb', 'Iron grades / '.$grade->code)

@section('content')
<div class="page-heading">
    <div><h1>{{ $grade->code }}</h1><p>{{ $grade->name }} · {{ $grade->family }}{{ $grade->standard_code ? ' · '.$grade->standard_code : '' }}</p></div>
    <div class="actions"><a class="btn" href="{{ route('iron-grades.index') }}">All grades</a></div>
</div>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="tab-marker" data-tab-start="Chemistry"></div>
<div class="panel">
    <div class="panel-header"><h2>Chemistry and process limits</h2></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('iron-grades.update', $grade) }}">@csrf @method('PUT')
            <input type="hidden" name="name" value="{{ $grade->name }}">
            <div class="table-wrap"><table>
                <thead><tr><th>Element</th><th>Min %</th><th>Max %</th></tr></thead>
                <tbody>
                @foreach (\App\Models\IronGrade::ELEMENTS as $k => $label)
                    <tr><td>{{ $label }}</td>
                        <td><input name="{{ $k }}_min" type="number" step="0.001" value="{{ old($k.'_min', $grade->{$k.'_min'}) }}"></td>
                        <td><input name="{{ $k }}_max" type="number" step="0.001" value="{{ old($k.'_max', $grade->{$k.'_max'}) }}"></td></tr>
                @endforeach
                    <tr><td>CE (C + Si/3)</td>
                        <td><input name="ce_min" type="number" step="0.01" value="{{ old('ce_min', $grade->ce_min) }}"></td>
                        <td><input name="ce_max" type="number" step="0.01" value="{{ old('ce_max', $grade->ce_max) }}"></td></tr>
                    <tr><td>Tapping temperature °C</td>
                        <td><input name="tap_temp_min_c" type="number" value="{{ old('tap_temp_min_c', $grade->tap_temp_min_c) }}"></td>
                        <td><input name="tap_temp_max_c" type="number" value="{{ old('tap_temp_max_c', $grade->tap_temp_max_c) }}"></td></tr>
                    <tr><td>Pouring temperature °C</td>
                        <td><input name="pour_temp_min_c" type="number" value="{{ old('pour_temp_min_c', $grade->pour_temp_min_c) }}"></td>
                        <td><input name="pour_temp_max_c" type="number" value="{{ old('pour_temp_max_c', $grade->pour_temp_max_c) }}"></td></tr>
                </tbody>
            </table></div>
            <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2">{{ old('notes', $grade->notes) }}</textarea></div>
            <button class="btn btn-primary" type="submit">Save limits</button>
        </form>
    </div>
</div>

<div class="tab-marker" data-tab-start="Thermal & furnaces"></div>
<div class="panel">
    <div class="panel-header"><h2>Thermal analysis acceptance window (grey / stable cup)</h2></div>
    <div class="panel-body">
        <p class="muted small">Set these from your own correlation of cooling curves with metallography and mechanical results. A field left empty is not judged. With every field empty, tests are recorded but not judged and the pouring gate does not ask for a thermal test.</p>
        <form method="POST" action="{{ route('iron-grades.window', $grade) }}">@csrf @method('PUT')
            <div class="form-grid">
                @foreach (['tl_min_c' => 'TL min °C', 'tl_max_c' => 'TL max °C', 'ce_min' => 'CE from TL min', 'ce_max' => 'CE from TL max', 'te_min_floor_c' => 'TEmin at least °C', 'recal_min_c' => 'Recalescence min °C', 'recal_max_c' => 'Recalescence max °C', 'solidus_min_c' => 'Solidus at least °C'] as $k => $l)
                    <div class="field"><label for="w_{{ $k }}">{{ $l }}</label><input id="w_{{ $k }}" name="{{ $k }}" type="number" step="0.1" value="{{ old($k, $window->{$k}) }}"></div>
                @endforeach
                <div class="field field-full"><label for="w_notes">Notes</label><input id="w_notes" name="notes" maxlength="1000" value="{{ old('notes', $window->notes) }}"></div>
            </div>
            <button class="btn btn-primary" type="submit">Save window</button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Thermal analysis acceptance window (white / tellurium cup)</h2></div>
    <div class="panel-body">
        <p class="muted small">The white cup solidifies without a eutectic arrest, so it is judged on the liquidus, the carbon equivalent and the solidus only. When a sample has both cups and a CE limit is set here, CE is judged on the white cup and the grey cup's CE limit is not applied to that sample.
            @if (! $whiteWindow->exists && ($grade->ce_min !== null || $grade->ce_max !== null))The CE boxes start from this grade's own chemistry limits ({{ $grade->ce_min }} to {{ $grade->ce_max }}); change them if your white cup reads differently.@endif</p>
        <form method="POST" action="{{ route('iron-grades.window.white', $grade) }}">@csrf @method('PUT')
            @php $startCe = ['ce_min' => $grade->ce_min, 'ce_max' => $grade->ce_max]; @endphp
            <div class="form-grid">
                @foreach (['tl_min_c' => 'TL min °C', 'tl_max_c' => 'TL max °C', 'ce_min' => 'CE from TL min', 'ce_max' => 'CE from TL max', 'solidus_min_c' => 'Solidus at least °C'] as $k => $l)
                    <div class="field"><label for="ww_{{ $k }}">{{ $l }}</label><input id="ww_{{ $k }}" name="{{ $k }}" type="number" step="0.01" value="{{ old($k, $whiteWindow->exists ? $whiteWindow->{$k} : ($startCe[$k] ?? null)) }}"></div>
                @endforeach
                <div class="field field-full"><label for="ww_notes">Notes</label><input id="ww_notes" name="notes" maxlength="1000" value="{{ old('notes', $whiteWindow->notes) }}"></div>
            </div>
            <button class="btn btn-primary" type="submit">Save white cup window</button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Furnace capability</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Furnace</th><th>Type</th><th>Can melt this grade</th><th>Basis</th><th>Override</th></tr></thead>
        <tbody>
        @forelse ($furnaces as $f)
            <tr>
                <td><strong>{{ $f->code }}</strong> {{ $f->name }}</td><td>{{ $f->furnace_type }}</td>
                <td>{{ $can[$f->id] ? 'Yes' : 'No' }}</td>
                <td>{{ $overrides->has($f->id) ? 'Set for this furnace' : 'Default for '.$f->furnace_type }}</td>
                <td><form method="POST" action="{{ route('iron-grades.capability', $grade) }}">@csrf
                    <input type="hidden" name="furnace_id" value="{{ $f->id }}">
                    <select name="mode"><option value="DEFAULT">Use default</option><option value="ALLOW" @selected($overrides->get($f->id) === true)>Allow</option><option value="DENY" @selected($overrides->has($f->id) && $overrides->get($f->id) === false)>Deny</option></select>
                    <button class="btn" type="submit">Save</button></form></td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty-state">No active furnaces.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="panel-body muted small">Default: induction furnaces can melt every family; cupolas only grey iron. Change the defaults in config/foundry.php.</div>
</div>

<div class="tab-marker" data-tab-start="Designations & limits"></div>
<div class="panel">
    <div class="panel-header"><h2>Equivalent designations</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Standard</th><th>Designation</th><th>Match</th></tr></thead>
        <tbody>@foreach ($grade->aliases->sortBy('standard') as $a)<tr><td>{{ $a->standard }}</td><td>{{ $a->designation }}</td><td>{{ $a->is_exact ? 'Same grade' : 'Nearest equivalent — confirm with the customer' }}</td></tr>@endforeach</tbody>
    </table></div>
</div>
@include('limits._panel', ['type' => 'IRON_GRADE', 'id' => $grade->id])
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('attachments._panel', ['type' => 'iron_grade', 'id' => $grade->id])
@include('audit._history', ['type' => 'IronGrade', 'id' => $grade->id])
@endsection
