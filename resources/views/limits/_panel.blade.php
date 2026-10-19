@php
    $rows = \App\Models\CharacteristicLimit::where('subject_type', $type)->where('subject_id', $id)->orderBy('test_group')->orderBy('sort_order')->get();
    $groups = $type === 'SAND_SYSTEM' ? ['SAND' => 'Sand', 'MOULD' => 'Mould hardness'] : ['MECHANICAL' => 'Mechanical', 'MICROSTRUCTURE' => 'Microstructure'];
@endphp
<div class="panel">
    <div class="panel-header"><h2>{{ $title ?? 'Test requirements' }}</h2></div>
    <div class="panel-body">
        @if ($errors->has('limit'))<div class="alert alert-error">{{ $errors->first('limit') }}</div>@endif
        <p class="muted small">Minimum and maximum for each test. Tick "required" to stop a lot being released until a passing result exists for its heat. Results keep the limits they were judged against.</p>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Group</th><th>Characteristic</th><th>Min</th><th>Max</th><th>Unit</th><th>Required for release</th><th></th></tr></thead>
        <tbody>
        @forelse ($rows as $l)
            <form method="POST" action="{{ route('limits.update', $l) }}">@csrf @method('PUT')
            <tr><td>{{ $l->test_group }}</td><td>{{ $l->name }}</td>
                <td><input type="number" step="any" name="min_value" value="{{ $l->min_value }}" style="width:90px"></td>
                <td><input type="number" step="any" name="max_value" value="{{ $l->max_value }}" style="width:90px"></td>
                <td><input name="unit" value="{{ $l->unit }}" size="6"></td>
                <td><input type="checkbox" name="is_required_for_release" value="1" @checked($l->is_required_for_release)></td>
                <td><button class="btn">Save</button></form>
                <form method="POST" action="{{ route('limits.destroy', $l) }}" style="display:inline">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'name' => $l->name])</form></td></tr>
        @empty
            <tr><td colspan="7" class="muted">No limits set.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('limits.store') }}" class="form-grid">@csrf
            <input type="hidden" name="subject_type" value="{{ $type }}"><input type="hidden" name="subject_id" value="{{ $id }}">
            <label>Group<select name="test_group">@foreach ($groups as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label>Characteristic name<input name="name" required placeholder="e.g. Hardness"></label>
            <label>Key (optional)<input name="characteristic_key" placeholder="hardness_hb"><span class="muted small">Use a key from the entry form to attach limits to it (for example hardness_hb). Leave empty for a new test.</span></label>
            <label>Min<input type="number" step="any" name="min_value"></label><label>Max<input type="number" step="any" name="max_value"></label><label>Unit<input name="unit"></label>
            <label><input type="checkbox" name="is_required_for_release" value="1"> Required for release</label>
            <div><button class="btn btn-primary">Add limit</button></div>
        </form>
    </div>
</div>
