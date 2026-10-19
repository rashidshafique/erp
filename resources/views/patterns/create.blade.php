@extends('layouts.app')

@section('title', 'New pattern')
@section('breadcrumb', 'Patterns / New pattern')

@section('content')
<div class="page-heading">
    <div>
        <h1>New pattern</h1>
        <p>Create the physical tooling record and its first revision.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('patterns.index') }}">Back to patterns</a>
    </div>
</div>


<form method="POST" action="{{ route('patterns.store') }}">
    @csrf

    <div class="panel">
        <div class="panel-header"><h2>Physical tooling</h2></div>
        <div class="panel-body">
            <div class="form-grid" data-progressive="3">
                @include('patterns._tooling-fields', ['withSand' => true])
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Initial engineering revision</h2></div>
        <div class="panel-body">
            <div class="form-grid" data-progressive="3">
                <div class="field">
                    <label for="revision">Revision code *</label>
                    <input id="revision" name="revision" required maxlength="20"
                           value="{{ old('revision', 'A') }}">
                </div>
                <div class="field">
                    <label for="revision_status">Revision status *</label>
                    <select id="revision_status" name="revision_status" required>
                        @foreach ($revisionStatuses as $status)
                            <option value="{{ $status }}" @selected(old('revision_status', 'DRAFT') === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="parting_type">Parting arrangement</label>
                    <select id="parting_type" name="parting_type">
                        <option value="">Not specified</option>
                        @foreach ($partingTypes as $type)
                            <option value="{{ $type }}" @selected(old('parting_type') === $type)>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="cavities_per_mould">Cavities per mould</label>
                    <input id="cavities_per_mould" name="cavities_per_mould"
                           type="number" min="1" step="1" value="{{ old('cavities_per_mould') }}">
                </div>
                <div class="field">
                    <label for="layers_per_mould">Layers per mould</label>
                    <input id="layers_per_mould" name="layers_per_mould"
                           type="number" min="1" step="1" value="{{ old('layers_per_mould', 2) }}">
                </div>
                <div class="field">
                    <label for="pattern_weight_kg">Pattern / plate weight (kg)</label>
                    <input id="pattern_weight_kg" name="pattern_weight_kg"
                           type="number" min="0.0001" step="0.0001"
                           value="{{ old('pattern_weight_kg') }}">
                </div>
                <div class="field">
                    <label for="shrinkage_percent">Shrinkage allowance (%)</label>
                    <input id="shrinkage_percent" name="shrinkage_percent" type="number" min="0" max="9.999" step="0.001" value="{{ old('shrinkage_percent') }}" placeholder="e.g. 1.0">
                </div>
                <div class="field">
                    <label for="tolerance_grade">Casting tolerance grade (ISO 8062 CT)</label>
                    <select id="tolerance_grade" name="tolerance_grade">
                        <option value="">Not specified</option>
                        @foreach (\App\Models\PatternRevision::TOLERANCE_GRADES as $grade)<option value="{{ $grade }}" @selected((string) old('tolerance_grade') === (string) $grade)>CT {{ $grade }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="machining_allowance_grade">Machining allowance grade (RMAG)</label>
                    <select id="machining_allowance_grade" name="machining_allowance_grade">
                        <option value="">Not specified</option>
                        @foreach (\App\Models\PatternRevision::ALLOWANCE_GRADES as $grade)<option value="{{ $grade }}" @selected(old('machining_allowance_grade') === $grade)>{{ $grade }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="effective_from">Effective from</label>
                    <input id="effective_from" name="effective_from" type="date"
                           value="{{ old('effective_from') }}">
                </div>
                <div class="field">
                    <label for="effective_to">Effective to</label>
                    <input id="effective_to" name="effective_to" type="date"
                           value="{{ old('effective_to') }}">
                </div>
                <div class="field field-full">
                    <label for="change_description">Revision / change description</label>
                    <textarea id="change_description" name="change_description" rows="3">{{ old('change_description') }}</textarea>
                </div>
                <div class="field field-full">
                    <label for="engineering_notes">Engineering notes</label>
                    <textarea id="engineering_notes" name="engineering_notes" rows="3">{{ old('engineering_notes') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit">Save pattern and revision</button>
        <a class="btn" href="{{ route('patterns.index') }}">Cancel</a>
    </div>
</form>
@endsection
