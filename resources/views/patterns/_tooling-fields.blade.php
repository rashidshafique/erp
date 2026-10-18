{{-- The physical tooling fields of a pattern, for the create and edit forms. $pattern (new or saved), $patternTypes,
     $patternStatuses; $withSand adds the sand system fields (the edit page has its own sand tab). --}}
<div class="field">
    <label for="code">Pattern code{{ $pattern->exists ? ' *' : '' }}</label>
    <input id="code" name="code" maxlength="40" @if ($pattern->exists) required @endif
           value="{{ old('code', $pattern->code) }}" placeholder="{{ $pattern->exists ? '' : 'Leave blank to generate: '.app(\App\Services\Codes\CodeGenerator::class)->preview('pattern') }}">
    @if ($pattern->exists)<div class="help">Revision codes already issued keep the code they were made with.</div>@endif
</div>
<div class="field">
    <label for="mark_code">Engraved mark</label>
    <input id="mark_code" name="mark_code" minlength="3" maxlength="20" pattern="[A-Za-z0-9]+" value="{{ old('mark_code', $pattern->mark_code) }}" placeholder="Optional" style="font-family:ui-monospace,Menlo,monospace;letter-spacing:2px;text-transform:uppercase">
    <div class="help">Optional. Fill it in only if the tool is already engraved with a number. Letters and digits, unique across all tools.</div>
</div>
<div class="field">
    <label for="name">Pattern name *</label>
    <input id="name" name="name" required maxlength="255" value="{{ old('name', $pattern->name) }}" placeholder="Part or tooling name">
</div>
<div class="field">
    <label for="pattern_type">Pattern type *</label>
    <select id="pattern_type" name="pattern_type" required>
        @foreach ($patternTypes as $type)
            <option value="{{ $type }}" @selected(old('pattern_type', $pattern->pattern_type ?? 'MATCHPLATE') === $type)>{{ $type }}</option>
        @endforeach
    </select>
    @if ($pattern->exists)<div class="help">Changing the type also sets the current revision's arrangement below, unless you chose another one.</div>@endif
</div>
<div class="field">
    <label for="status">Tooling status *</label>
    <select id="status" name="status" required>
        @foreach ($patternStatuses as $status)
            <option value="{{ $status }}" @selected(old('status', $pattern->status ?? 'ACTIVE') === $status)>{{ $status }}</option>
        @endforeach
    </select>
</div>
<div class="field">
    <label for="condition_grade">Condition</label>
    <select id="condition_grade" name="condition_grade">
        <option value="">Not graded</option>
        @foreach (\App\Models\Pattern::CONDITIONS as $grade => $meaning)
            <option value="{{ $grade }}" @selected(old('condition_grade', $pattern->condition_grade) === $grade)>{{ $grade }} · {{ $meaning }}</option>
        @endforeach
    </select>
    <div class="help">Grade it after inspection or repair. A = good, B = worn but usable, C = needs repair, D = unfit for use.</div>
</div>
<div class="field">
    <label for="material">Pattern / plate material</label>
    <input id="material" name="material" maxlength="50" value="{{ old('material', $pattern->material) }}" placeholder="e.g. Aluminium, wood, resin">
</div>
<div class="field">
    <label for="length_mm">Length (mm)</label>
    <input id="length_mm" name="length_mm" type="number" min="0.01" step="0.01" value="{{ old('length_mm', $pattern->length_mm) }}">
</div>
<div class="field">
    <label for="width_mm">Width (mm)</label>
    <input id="width_mm" name="width_mm" type="number" min="0.01" step="0.01" value="{{ old('width_mm', $pattern->width_mm) }}">
</div>
<div class="field">
    <label for="height_mm">Height (mm)</label>
    <input id="height_mm" name="height_mm" type="number" min="0.01" step="0.01" value="{{ old('height_mm', $pattern->height_mm) }}">
</div>
@if (! empty($withSand))
    @include('patterns._sand')
@endif
<div class="field field-full">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="3">{{ old('description', $pattern->description) }}</textarea>
</div>
<div class="field field-full">
    <label for="notes">General tooling notes</label>
    <textarea id="notes" name="notes" rows="3">{{ old('notes', $pattern->notes) }}</textarea>
</div>
