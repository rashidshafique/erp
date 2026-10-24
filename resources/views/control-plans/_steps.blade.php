{{-- A control plan laid along the process: each step of the process with the things controlled at that step. Editable when $action is given. --}}
@php
    $editable = isset($action);
    $stageIds = $stages->pluck('id');
    $groups = [];
    $general = $items->filter(fn ($item) => $item->stage_definition_id === null);
    if ($editable || $general->isNotEmpty()) {
        $groups[] = ['label' => 'Whole process', 'hint' => 'Controlled at no one step', 'stage' => null, 'items' => $general];
    }
    foreach ($stages as $position => $stage) {
        $groups[] = ['label' => ($position + 1).'. '.$stage->name, 'hint' => null, 'stage' => $stage->id, 'items' => $items->where('stage_definition_id', $stage->id)];
    }
    foreach ($editable ? $items->filter(fn ($item) => $item->stage_definition_id !== null && ! $stageIds->contains($item->stage_definition_id))->groupBy('stage_definition_id') : [] as $stageId => $orphans) {
        $groups[] = ['label' => $orphans->first()->stageDefinition?->name ?? 'Unknown step', 'hint' => 'Not a step of this process; clear the rows to remove them', 'stage' => $stageId, 'items' => $orphans];
    }
    $number = fn ($value) => $value !== null ? rtrim(rtrim((string) $value, '0'), '.') : '';
    $i = 0;
@endphp
@if ($editable)
<form method="POST" action="{{ $action }}">
    @csrf @method('PUT')
@endif
@foreach ($groups as $group)
    @php $rows = $editable ? $group['items']->concat([null]) : $group['items']; @endphp
    @if ($editable)
        <details style="margin-bottom:8px" @if ($group['items']->isNotEmpty()) open @endif>
            <summary><strong>{{ $group['label'] }}</strong> <span class="muted small">{{ $group['items']->isEmpty() ? 'nothing controlled yet' : $group['items']->count().' controlled' }}{{ $group['hint'] ? ' · '.$group['hint'] : '' }}</span></summary>
    @else
        <div style="margin-bottom:8px"><strong>{{ $group['label'] }}</strong>
        @if ($group['items']->isEmpty())<span class="muted small">nothing controlled</span>@endif
    @endif
    @if ($rows->isNotEmpty())
        <div class="table-wrap"><table>
            @if ($editable)
                <thead><tr><th>What is controlled</th><th>Min</th><th>Target</th><th>Max</th><th>Unit</th><th>How measured</th><th>How often</th><th>If out of limits</th></tr></thead>
                <tbody>@foreach ($rows as $item)
                    <tr>
                        <td><input type="hidden" name="rows[{{ $i }}][stage_definition_id]" value="{{ $group['stage'] }}"><input name="rows[{{ $i }}][parameter_name]" maxlength="200" value="{{ $item?->parameter_name }}" aria-label="What is controlled"></td>
                        @foreach (['minimum_value', 'target_value', 'maximum_value'] as $f)<td><input name="rows[{{ $i }}][{{ $f }}]" type="number" step="any" value="{{ $number($item?->$f) }}" style="width:90px" aria-label="{{ $f }}"></td>@endforeach
                        <td><input name="rows[{{ $i }}][unit]" maxlength="40" value="{{ $item?->unit }}" style="width:70px" aria-label="Unit"></td>
                        <td><input name="rows[{{ $i }}][control_method]" maxlength="500" value="{{ $item?->control_method }}" aria-label="How measured"></td>
                        <td><input name="rows[{{ $i }}][monitoring_frequency]" maxlength="50" value="{{ $item?->monitoring_frequency }}" style="width:120px" aria-label="How often"></td>
                        <td><input name="rows[{{ $i }}][reaction_plan]" maxlength="1000" value="{{ $item?->reaction_plan }}" aria-label="If out of limits"></td>
                    </tr>
                    @php $i++; @endphp
                @endforeach</tbody>
            @else
                <thead><tr><th>What is controlled</th><th>Limits</th><th>How measured</th><th>How often</th><th>If out of limits</th></tr></thead>
                <tbody>@foreach ($rows as $item)
                    <tr><td>{{ $item->parameter_name }}</td>
                        <td>{{ $number($item->minimum_value) }}{{ $item->target_value !== null ? ' / '.$number($item->target_value) : '' }}{{ $item->maximum_value !== null ? ' / '.$number($item->maximum_value) : '' }} {{ $item->unit }}</td>
                        <td>{{ $item->control_method }}</td><td>{{ $item->monitoring_frequency }}</td><td>{{ $item->reaction_plan }}</td></tr>
                @endforeach</tbody>
            @endif
        </table></div>
    @endif
    @if ($editable) </details> @else </div> @endif
@endforeach
@if ($editable)
    <div class="actions" style="margin-top:12px"><button class="btn btn-primary" type="submit">{{ $submit ?? 'Save rows' }}</button><span class="muted small">Clear the name of a row to remove it. The last row of each step is always blank, ready for the next control.</span></div>
</form>
@endif
