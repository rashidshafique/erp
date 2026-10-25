@extends('layouts.app')

@section('title', 'Edit '.$revision->code)
@section('breadcrumb')<a href="{{ route('patterns.index') }}">Patterns</a> / <a href="{{ route('patterns.show', $pattern) }}">{{ $pattern->code }}</a> / {{ $revision->code }} @endsection

@section('content')
@php $f = fn ($field) => old($field, $revision->{$field}); @endphp
<div class="page-heading">
    <div>
        <h1>Edit details of {{ $revision->code }}</h1>
        <p>Fill in what is missing, or correct a recorded value. Filling a blank is free; changing a value already recorded needs a reason, which goes in the audit trail. The code, status and mould layout are not changed here.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('patterns.show', $pattern) }}">Back to pattern</a>
    </div>
</div>

<form method="POST" action="{{ route('patterns.revisions.update', [$pattern, $revision]) }}">
    @csrf @method('PUT')
    <div class="panel">
        <div class="panel-header"><h2>{{ $pattern->name }} · {{ $revision->code }}</h2></div>
        <div class="panel-body">
            <div class="form-grid">
                <div class="field"><label for="drawing_revision">Customer drawing revision</label><input id="drawing_revision" name="drawing_revision" maxlength="40" value="{{ $f('drawing_revision') }}"></div>
                <div class="field"><label for="parting_type">Parting arrangement</label>
                    <select id="parting_type" name="parting_type"><option value="">Not specified</option>@foreach ($partingTypes as $type)<option value="{{ $type }}" @selected($f('parting_type') === $type)>{{ $type }}</option>@endforeach</select></div>
                <div class="field"><label for="shrinkage_percent">Shrinkage allowance (%)</label><input id="shrinkage_percent" type="number" name="shrinkage_percent" min="0" max="9.999" step="any" value="{{ $f('shrinkage_percent') }}"></div>
                <div class="field"><label for="tolerance_grade">Casting tolerance (ISO 8062 CT)</label>
                    <select id="tolerance_grade" name="tolerance_grade"><option value="">Not specified</option>@foreach (\App\Models\PatternRevision::TOLERANCE_GRADES as $g)<option value="{{ $g }}" @selected((string) $f('tolerance_grade') === (string) $g)>CT {{ $g }}</option>@endforeach</select></div>
                <div class="field"><label for="machining_allowance_grade">Machining allowance (RMAG)</label>
                    <select id="machining_allowance_grade" name="machining_allowance_grade"><option value="">Not specified</option>@foreach (\App\Models\PatternRevision::ALLOWANCE_GRADES as $g)<option value="{{ $g }}" @selected($f('machining_allowance_grade') === $g)>{{ $g }}</option>@endforeach</select></div>
                <div class="field"><label for="pattern_weight_kg">Pattern / plate weight (kg)</label><input id="pattern_weight_kg" type="number" name="pattern_weight_kg" min="0.0001" step="any" value="{{ $f('pattern_weight_kg') }}"></div>
                @if ($severalProducts)
                    <div class="field"><label>Casting weight</label><p class="muted small" style="margin:0">This revision's cavities make several products, so the casting weight is recorded per cavity on the <a href="{{ route('patterns.show', $pattern) }}#cavities-{{ $revision->id }}">cavity map</a>.</p></div>
                @else
                    <div class="field"><label for="casting_weight_kg">Casting weight, one piece (kg)</label><input id="casting_weight_kg" type="number" name="casting_weight_kg" min="0.0001" step="any" value="{{ $f('casting_weight_kg') }}"></div>
                @endif
                <div class="field"><label for="pour_weight_kg">Poured per casting, incl. gating (kg)</label><input id="pour_weight_kg" type="number" name="pour_weight_kg" min="0.0001" step="any" value="{{ $f('pour_weight_kg') }}"></div>
                <div class="field"><label for="pieces_per_mould">Pieces per mould</label><input id="pieces_per_mould" type="number" name="pieces_per_mould" min="1" max="10000" value="{{ $f('pieces_per_mould') }}"></div>
                <div class="field"><label for="modification_cost">Modification cost (PKR)</label><input id="modification_cost" type="number" name="modification_cost" min="0" step="any" value="{{ $f('modification_cost') }}"></div>
                <div class="field"><label for="effective_from">Effective from</label><input id="effective_from" type="date" name="effective_from" value="{{ $f('effective_from') instanceof \Carbon\CarbonInterface ? $f('effective_from')->toDateString() : $f('effective_from') }}"></div>
                <div class="field"><label for="effective_to">Effective to</label><input id="effective_to" type="date" name="effective_to" value="{{ $f('effective_to') instanceof \Carbon\CarbonInterface ? $f('effective_to')->toDateString() : $f('effective_to') }}"></div>
                <div class="field field-full"><label for="shrinkage_note">Shrinkage / allowance note</label><input id="shrinkage_note" name="shrinkage_note" maxlength="2000" value="{{ $f('shrinkage_note') }}"></div>
                <div class="field field-full"><label for="cost_note">Cost note</label><input id="cost_note" name="cost_note" maxlength="2000" value="{{ $f('cost_note') }}"></div>
                <div class="field field-full"><label for="change_description">What changed in this revision</label><textarea id="change_description" name="change_description" rows="3" maxlength="2000">{{ $f('change_description') }}</textarea></div>
                <div class="field field-full"><label for="engineering_notes">Engineering notes</label><textarea id="engineering_notes" name="engineering_notes" rows="3">{{ $f('engineering_notes') }}</textarea></div>
                <div class="field field-full"><label for="correction_reason">Why is a recorded value being changed?</label>
                    <input id="correction_reason" name="correction_reason" maxlength="500" value="{{ old('correction_reason') }}" placeholder="Needed only when changing or clearing a value already recorded">
                    @error('correction_reason')<div class="error-text">{{ $message }}</div>@enderror</div>
            </div>
        </div>
    </div>
    <div class="actions">
        <button class="btn btn-primary" type="submit">Save details</button>
        <a class="btn" href="{{ route('patterns.show', $pattern) }}">Cancel</a>
    </div>
</form>
@endsection
