{{-- Limits for the chosen sand system, or all of them; the system for a new limit is picked on the add form. $systems, $system (null = all), $systemNames. --}}
@php
    $limits = \App\Models\CharacteristicLimit::where('subject_type', 'SAND_SYSTEM')->when($system, fn ($q) => $q->where('subject_id', $system->id))->get()->sortBy([fn ($l) => $systemNames[$l->subject_id] ?? '', 'test_group', 'sort_order']);
    $groups = ['SAND' => 'Sand', 'MOULD' => 'Mould hardness'];
@endphp
<div class="panel">
    <div class="panel-header"><h2>{{ $system ? 'Limits for '.$system->name.' sand' : 'Limits for all sands' }}</h2></div>
    <div class="panel-body">
        @if ($errors->has('limit'))<div class="alert alert-error">{{ $errors->first('limit') }}</div>@endif
        <p class="muted small">Minimum and maximum for each test. Tick "required" to stop a lot being released until a passing result exists for its heat. Results keep the limits they were judged against.</p>
    </div>
    <div class="table-wrap"><table data-col-filter>
        <thead><tr><th>Sand system</th><th>Group</th><th>Characteristic</th><th>Min</th><th>Max</th><th>Unit</th><th>Required for release</th><th></th></tr></thead>
        <tbody>
        @forelse ($limits as $l)
            <form method="POST" action="{{ route('limits.update', $l) }}">@csrf @method('PUT')
            <tr><td>{{ $systemNames[$l->subject_id] ?? '—' }}</td><td>{{ $l->test_group }}</td><td>{{ $l->name }}</td>
                <td><input type="number" step="any" name="min_value" value="{{ $l->min_value }}" style="width:90px"></td>
                <td><input type="number" step="any" name="max_value" value="{{ $l->max_value }}" style="width:90px"></td>
                <td><input name="unit" value="{{ $l->unit }}" size="6"></td>
                <td><input type="checkbox" name="is_required_for_release" value="1" @checked($l->is_required_for_release)></td>
                <td><button class="btn">Save</button></form>
                <form method="POST" action="{{ route('limits.destroy', $l) }}" style="display:inline">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'name' => $l->name])</form></td></tr>
        @empty
            <tr><td colspan="8" class="muted">No limits set.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('limits.store') }}" class="form-grid">@csrf
            <input type="hidden" name="subject_type" value="SAND_SYSTEM">
            <label>Sand system<select name="subject_id" required>@if (! $system)<option value="">Choose…</option>@endif @foreach ($systems as $x)<option value="{{ $x->id }}" @selected($system?->id === $x->id)>{{ $x->name }}</option>@endforeach</select></label>
            <label>Group<select name="test_group">@foreach ($groups as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label>Characteristic name<input name="name" required placeholder="e.g. Hardness"></label>
            <label>Key (optional)<input name="characteristic_key" placeholder="hardness_hb"><span class="muted small">Use a key from the entry form to attach limits to it. Leave empty for a new test.</span></label>
            <label>Min<input type="number" step="any" name="min_value"></label><label>Max<input type="number" step="any" name="max_value"></label><label>Unit<input name="unit"></label>
            <label><input type="checkbox" name="is_required_for_release" value="1"> Required for release</label>
            <div><button class="btn btn-primary">Add limit</button></div>
        </form>
    </div>
</div>
