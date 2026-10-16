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

@if ($errors->any())
    <div class="alert alert-error">
        <strong>Please correct the following:</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('patterns.store') }}">
    @csrf

    <div class="panel">
        <div class="panel-header"><h2>Physical tooling</h2></div>
        <div class="panel-body">
            <div class="form-grid" data-progressive="3">
                <div class="field">
                    <label for="code">Pattern code</label>
                    <input id="code" name="code" maxlength="40"
                           value="{{ old('code') }}" placeholder="Leave blank to generate: {{ app(\App\Services\Codes\CodeGenerator::class)->preview('pattern') }}">
                </div>
                <div class="field">
                    <label for="name">Pattern name *</label>
                    <input id="name" name="name" required maxlength="255"
                           value="{{ old('name') }}" placeholder="Part or tooling name">
                </div>
                <div class="field">
                    <label for="pattern_type">Pattern type *</label>
                    <select id="pattern_type" name="pattern_type" required>
                        @foreach ($patternTypes as $type)
                            <option value="{{ $type }}" @selected(old('pattern_type', 'MATCHPLATE') === $type)>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="status">Tooling status *</label>
                    <select id="status" name="status" required>
                        @foreach ($patternStatuses as $status)
                            <option value="{{ $status }}" @selected(old('status', 'ACTIVE') === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="material">Pattern / plate material</label>
                    <input id="material" name="material" maxlength="50"
                           value="{{ old('material') }}" placeholder="e.g. Aluminium, wood, resin">
                </div>
                <div class="field">
                    <label for="length_mm">Length (mm)</label>
                    <input id="length_mm" name="length_mm" type="number" min="0.01" step="0.01"
                           value="{{ old('length_mm') }}">
                </div>
                <div class="field">
                    <label for="width_mm">Width (mm)</label>
                    <input id="width_mm" name="width_mm" type="number" min="0.01" step="0.01"
                           value="{{ old('width_mm') }}">
                </div>
                <div class="field">
                    <label for="height_mm">Height (mm)</label>
                    <input id="height_mm" name="height_mm" type="number" min="0.01" step="0.01"
                           value="{{ old('height_mm') }}">
                </div>
                @include('patterns._sand')
                <div class="field field-full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="field field-full">
                    <label for="notes">General tooling notes</label>
                    <textarea id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                </div>
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
