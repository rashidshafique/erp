{{-- The rows of a control plan as an editable table: what is controlled at which step, to what limits, how and how often. Three blank rows are always offered. --}}
@php $blank = 3; $stages = \App\Models\ProductionStageDefinition::where('is_active', true)->orderBy('sequence')->get(['id', 'name']); @endphp
<form method="POST" action="{{ $action }}">
    @csrf @method('PUT')
    <div class="table-wrap"><table>
        <thead><tr><th>Step</th><th>What is controlled</th><th>Min</th><th>Target</th><th>Max</th><th>Unit</th><th>How measured</th><th>How often</th><th>If out of limits</th></tr></thead>
        <tbody>
        @foreach ($items->concat(array_fill(0, $blank, null)) as $i => $item)
            <tr>
                <td><select name="rows[{{ $i }}][stage_definition_id]" aria-label="Step"><option value="">Any</option>@foreach ($stages as $stage)<option value="{{ $stage->id }}" @selected($item?->stage_definition_id === $stage->id)>{{ $stage->name }}</option>@endforeach</select></td>
                <td><input name="rows[{{ $i }}][parameter_name]" maxlength="200" value="{{ $item?->parameter_name }}" aria-label="What is controlled"></td>
                @foreach (['minimum_value', 'target_value', 'maximum_value'] as $f)<td><input name="rows[{{ $i }}][{{ $f }}]" type="number" step="any" value="{{ $item?->$f !== null ? rtrim(rtrim((string) $item->$f, '0'), '.') : '' }}" style="width:90px" aria-label="{{ $f }}"></td>@endforeach
                <td><input name="rows[{{ $i }}][unit]" maxlength="40" value="{{ $item?->unit }}" style="width:70px" aria-label="Unit"></td>
                <td><input name="rows[{{ $i }}][control_method]" maxlength="500" value="{{ $item?->control_method }}" aria-label="How measured"></td>
                <td><input name="rows[{{ $i }}][monitoring_frequency]" maxlength="50" value="{{ $item?->monitoring_frequency }}" style="width:120px" aria-label="How often"></td>
                <td><input name="rows[{{ $i }}][reaction_plan]" maxlength="1000" value="{{ $item?->reaction_plan }}" aria-label="If out of limits"></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="actions" style="margin-top:12px"><button class="btn btn-primary" type="submit">{{ $submit ?? 'Save rows' }}</button><span class="muted small">Clear the name of a row to remove it.</span></div>
</form>
