@extends('layouts.app')

@section('title', 'Edit '.$pattern->name)
@section('breadcrumb', 'Patterns / '.$pattern->code.' / Edit')

@section('content')
<div class="page-heading">
    <div>
        <h1>Edit {{ $pattern->name }}</h1>
        <p>Correct the tooling record and the current revision's mould layout. Changes are kept in the audit trail. If the tooling itself was changed, record a new revision instead.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('patterns.show', $pattern) }}">Back to pattern</a>
    </div>
</div>

<form method="POST" action="{{ route('patterns.update', $pattern) }}">
    @csrf @method('PUT')
    <div class="panel">
        <div class="panel-header"><h2>Physical tooling</h2></div>
        <div class="panel-body">
            <div class="form-grid">
                @include('patterns._tooling-fields')
            </div>
        </div>
    </div>
    @if ($current)
        @php
            $lv = fn ($field, $default = null) => old($field, $current->{$field} ?? $default);
            $stacked = in_array($lv('mould_arrangement'), ['STACK', 'CORE_PACKAGE'], true);
        @endphp
        <div class="panel" id="layout">
            <div class="panel-header"><h2>Mould layout of {{ $current->code }}</h2><span class="muted small">The current revision. Older revisions keep their own layout.</span></div>
            <div class="panel-body">
                @if ($assembled)
                    <div class="alert alert-warning">{{ $assembled }} {{ $assembled === 1 ? 'mould was' : 'moulds were' }} assembled with this layout. Change it only to correct a mistake: those moulds keep the castings they recorded. If the tooling itself changed, record a new revision instead.</div>
                @endif
                <div class="layout-edit">
                    <div>@include('patterns._mould-diagram', ['revision' => $current, 'size' => 'lg'])</div>
                    <div class="form-grid">
                        <div class="field field-full"><label for="mould_arrangement">Arrangement</label>
                            <select id="mould_arrangement" name="mould_arrangement" data-type-defaults='@json(\App\Models\Pattern::TYPES)'>@foreach (\App\Models\PatternRevision::ARRANGEMENTS as $k => $l)<option value="{{ $k }}" @selected($lv('mould_arrangement') === $k)>{{ $l }}</option>@endforeach</select></div>
                        <div class="field" data-stack-only @unless ($stacked) hidden @endunless><label for="layers_per_mould">Moulds in the stack</label>
                            <input id="layers_per_mould" type="number" name="layers_per_mould" min="2" max="50" value="{{ $lv('layers_per_mould', 2) }}" @unless ($stacked) disabled @endunless>
                            <div class="help">Stack height: every section, the bottom and top ones included.</div></div>
                        <div class="field" data-stack-only @unless ($stacked) hidden @endunless><label for="casting_levels">Casting levels</label>
                            <input id="casting_levels" type="number" name="casting_levels" min="1" max="50" value="{{ $lv('casting_levels') }}" placeholder="moulds − 1" @unless ($stacked) disabled @endunless>
                            <div class="help" data-levels-hint></div></div>
                        <div class="field"><label for="cavities_per_mould">Cavities on the layout</label>
                            <input id="cavities_per_mould" type="number" name="cavities_per_mould" min="1" max="10000" value="{{ $lv('cavities_per_mould') }}"><div class="help">On one level.</div></div>
                        @include('patterns._flask-sizes-field', ['revision' => $current, 'mine' => old('flask_sizes_sent') !== null])
                        @if ($assembled)
                            <div class="field field-full"><label for="correction_reason">Why is the layout being corrected?</label>
                                <input id="correction_reason" name="correction_reason" maxlength="500" value="{{ old('correction_reason') }}" placeholder="Needed only when the layout changes">
                                @error('correction_reason')<div class="error-text">{{ $message }}</div>@enderror</div>
                        @endif
                    </div>
                </div>
                <p class="muted small" style="margin-bottom:0">Runner, sprue and weighed totals are set on the cavity map.</p>
            </div>
        </div>
        <style>
            .layout-edit { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 18px 28px; align-items: start; }
            @media (max-width: 768px) { .layout-edit { grid-template-columns: 1fr; } }
        </style>
        <script>
        (function () {
            var type = document.getElementById('pattern_type'), arr = document.getElementById('mould_arrangement');
            var defaults = JSON.parse(arr.dataset.typeDefaults), lastType = type.value;
            var sections = document.getElementById('layers_per_mould'), levels = document.getElementById('casting_levels'), hint = document.querySelector('[data-levels-hint]');
            function sync() {
                var stacked = arr.value === 'STACK' || arr.value === 'CORE_PACKAGE';
                document.querySelectorAll('[data-stack-only]').forEach(function (f) { f.hidden = !stacked; f.querySelectorAll('input').forEach(function (i) { i.disabled = !stacked; }); });
                if (stacked && (+sections.value || 0) < 3 && !sections.dataset.touched) {
                    sections.value = 7;                                   // a two-flask layout becoming a stack: start from a typical height
                    if ((+levels.value || 0) <= 1) levels.value = '';     // and let the levels follow it
                }
                var n = Math.max(1, (+sections.value || 2) - 1);
                levels.placeholder = n;
                hint.textContent = levels.value ? 'Joints holding castings.' : 'Left blank: ' + n + ' (moulds less one: the bottom and top close the stack).';
            }
            type.addEventListener('change', function () {
                if (arr.value === defaults[lastType]) arr.value = defaults[type.value] || 'OTHER';
                lastType = type.value; sync();
            });
            arr.addEventListener('change', sync);
            sections.addEventListener('input', function () { sections.dataset.touched = '1'; sync(); });
            levels.addEventListener('input', sync);
            sync();
        })();
        </script>
    @endif
    <div class="actions">
        <button class="btn btn-primary" type="submit">Save pattern</button>
        <a class="btn" href="{{ route('patterns.show', $pattern) }}">Cancel</a>
    </div>
</form>

<div style="margin-top:22px">@include('patterns._history', ['pattern' => $pattern])</div>
@endsection
