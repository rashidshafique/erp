@php
    $cf = app(\App\Services\CustomFields\CustomFields::class);
    $cfDefs = $cf->definitions($entity);
    $cfVals = $cfDefs->isEmpty() ? [] : $cf->values($entity, $id);
@endphp
<div class="panel">
    <div class="panel-header"><h2>Additional information</h2>
        <a class="small" href="{{ route('custom-fields.index') }}">Manage fields</a></div>
    <div class="panel-body">
    @if ($cfDefs->isEmpty())
        <p class="muted small">No additional fields are defined for {{ strtolower(\App\Services\CustomFields\CustomFields::ENTITIES[$entity][0]) }} records. Add them under Administration → Custom fields.</p>
    @else
        <form method="POST" action="{{ route('custom-fields.values', [$entity, $id]) }}" class="form-grid">@csrf
            @foreach ($cfDefs as $d)
                @php $v = old('cf.'.$d->field_key, $cfVals[$d->field_key] ?? null); @endphp
                <label>{{ $d->label }}@if($d->unit) ({{ $d->unit }})@endif @if($d->is_required)*@endif
                    @if ($d->data_type === 'SELECT')
                        <select name="cf[{{ $d->field_key }}]"><option value="">—</option>@foreach (($d->options ?? []) as $o)<option @selected($v === $o)>{{ $o }}</option>@endforeach</select>
                    @elseif ($d->data_type === 'BOOLEAN')
                        <select name="cf[{{ $d->field_key }}]"><option value="">—</option><option value="1" @selected($v === true || $v === '1')>Yes</option><option value="0" @selected($v === false || $v === '0')>No</option></select>
                    @elseif ($d->data_type === 'DATE')
                        <input type="date" name="cf[{{ $d->field_key }}]" value="{{ $v }}">
                    @elseif ($d->data_type === 'NUMBER')
                        <input type="number" step="any" name="cf[{{ $d->field_key }}]" value="{{ $v }}" @if($d->min_value !== null) min="{{ $d->min_value }}" @endif @if($d->max_value !== null) max="{{ $d->max_value }}" @endif>
                    @else
                        <input name="cf[{{ $d->field_key }}]" value="{{ $v }}" maxlength="500">
                    @endif
                    @if ($d->help_text)<span class="muted small">{{ $d->help_text }}</span>@endif
                    @error('cf.'.$d->field_key)<span style="color:var(--danger)">{{ $message }}</span>@enderror
                </label>
            @endforeach
            <div><button class="btn btn-primary">Save</button></div>
        </form>
    @endif
    </div>
</div>
