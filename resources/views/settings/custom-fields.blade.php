@extends('layouts.app')
@section('title', 'Custom fields')
@section('breadcrumb', 'Settings / Custom fields')
@section('content')
@php
    $record = array_key_exists(request('record', ''), $entities) ? request('record') : (collect($entities)->keys()->first(fn ($k) => isset($defs[$k])) ?? array_key_first($entities));
    $fields = $defs[$record] ?? collect();
    $keep = fn ($d) => ['label' => $d->label, 'options_text' => implode("\n", $d->options ?? []), 'unit' => $d->unit, 'min_value' => $d->min_value, 'max_value' => $d->max_value, 'help_text' => $d->help_text, 'sort_order' => $d->sort_order];
@endphp
<style>
    .cf-shell { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 20px; align-items: start; }
    .cf-records a { display: flex; justify-content: space-between; gap: 8px; padding: 7px 12px; border-radius: 6px; color: inherit; text-decoration: none; font-size: 13.5px; }
    .cf-records a:hover { background: var(--hover); }
    .cf-records a.active { background: var(--hover); font-weight: 500; color: var(--accent-dark); }
    .cf-records .n { color: var(--muted); font-size: 12px; }
    .cf-empty { padding: 32px 16px; text-align: center; color: var(--muted); }
    dialog.cf-new { border: 1px solid var(--border); border-radius: 10px; padding: 0; width: min(520px, 94vw); }
    dialog.cf-new::backdrop { background: rgba(0,0,0,.25); }
    @media (max-width: 800px) { .cf-shell { grid-template-columns: 1fr; } }
</style>
<div class="page-heading">
    <div><h1>Custom fields</h1><p>Extra details on records, e.g. drawing refs, box sizes. Test results go in inspection plans, not here.</p></div>
    <div class="actions"><button type="button" class="btn btn-primary" onclick="document.getElementById('cf-new').showModal()">New field on {{ $entities[$record][0] }}</button></div>
</div>

<div class="cf-shell">
    <nav class="cf-records panel" style="padding:8px" aria-label="Records">
        @foreach ($entities as $key => $e)
            <a href="{{ route('custom-fields.index', ['record' => $key]) }}" @class(['active' => $key === $record])><span>{{ $e[0] }}</span><span class="n">{{ isset($defs[$key]) ? $defs[$key]->count() : 0 }}</span></a>
        @endforeach
    </nav>

    <div class="panel">
        <div class="panel-header"><h2>{{ $entities[$record][0] }}</h2><span class="muted small">Fields appear in the order of the Order column</span></div>
        @if ($fields->isEmpty())
            <div class="cf-empty"><strong>No custom fields on {{ $entities[$record][0] }}</strong><br>Add one if you track something the standard form does not have.</div>
        @else
        <div class="table-wrap"><table>
            <thead><tr><th>Label</th><th>Type</th><th>Details</th><th>Required</th><th>Order</th><th>Active</th><th></th></tr></thead>
            <tbody>
            @foreach ($fields as $d)
                <tr @if (! $d->is_active) style="opacity:.55" @endif>
                    <td><strong>{{ $d->label }}</strong><br><code class="muted small">{{ $d->field_key }}</code></td>
                    <td>{{ $types[$d->data_type] }}</td>
                    <td class="muted small">{{ collect([$d->options ? count($d->options).' choices' : null, $d->unit, ($d->min_value !== null || $d->max_value !== null) ? ($d->min_value ?? '…').' to '.($d->max_value ?? '…') : null])->filter()->implode(' · ') }}</td>
                    <td>{{ $d->is_required ? 'Yes' : 'No' }}</td>
                    <td>{{ $d->sort_order }}</td>
                    <td><input class="switch" type="checkbox" name="is_active" value="1" form="toggle-{{ $d->id }}" @checked($d->is_active) onchange="this.form.submit()" aria-label="Active"></td>
                    <td><button type="button" class="btn btn-small" onclick="document.getElementById('cf-edit-{{ $d->id }}').showModal()">Edit</button></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @endif
    </div>
</div>

@foreach ($fields as $d)
    <form id="toggle-{{ $d->id }}" method="POST" action="{{ route('custom-fields.update', $d) }}">@csrf @method('PUT')
        @foreach ($keep($d) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
        @if ($d->is_required)<input type="hidden" name="is_required" value="1">@endif
    </form>
    <dialog class="cf-new" id="cf-edit-{{ $d->id }}">
        <form method="POST" action="{{ route('custom-fields.update', $d) }}" class="panel-body">@csrf @method('PUT')
            <h2 style="margin-top:0">{{ $d->label }}</h2>
            <p class="muted small"><code>{{ $d->field_key }}</code> · {{ $types[$d->data_type] }} — the key and type stay fixed so saved values keep their meaning.</p>
            <div class="form-grid">
                <label>Label<input name="label" value="{{ $d->label }}" required></label>
                <label>Help text<input name="help_text" value="{{ $d->help_text }}" maxlength="255"></label>
                @if ($d->data_type === 'SELECT')<label>Choices, one per line<textarea name="options_text" rows="4">{{ implode("\n", $d->options ?? []) }}</textarea></label>@endif
                <label>Unit<input name="unit" value="{{ $d->unit }}" maxlength="20"></label>
                <label>Min<input type="number" step="any" name="min_value" value="{{ $d->min_value }}"></label>
                <label>Max<input type="number" step="any" name="max_value" value="{{ $d->max_value }}"></label>
                <label>Order<input type="number" name="sort_order" value="{{ $d->sort_order }}"></label>
                <label><input class="switch" type="checkbox" name="is_required" value="1" @checked($d->is_required)> Required before the record can be saved</label>
                <label><input class="switch" type="checkbox" name="is_active" value="1" @checked($d->is_active)> Active</label>
            </div>
            <div class="actions" style="margin-top:12px"><button type="button" class="btn" onclick="this.closest('dialog').close()">Cancel</button><button class="btn btn-primary">Save</button></div>
        </form>
    </dialog>
@endforeach

<dialog class="cf-new" id="cf-new" @if ($errors->any() && old('label') !== null) data-open @endif>
    <form method="POST" action="{{ route('custom-fields.store') }}" class="panel-body">@csrf
        <input type="hidden" name="entity_type" value="{{ $record }}">
        <h2 style="margin-top:0">New field on {{ $entities[$record][0] }}</h2>
        @if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        <div class="form-grid">
            <label>Label<input name="label" value="{{ old('label') }}" required></label>
            <label>Type<select name="data_type">@foreach ($types as $k => $l)<option value="{{ $k }}" @selected(old('data_type') === $k)>{{ $l }}</option>@endforeach</select></label>
            <label>Choices (for choice lists, one per line)<textarea name="options_text" rows="3">{{ old('options_text') }}</textarea></label>
            <label>Unit<input name="unit" value="{{ old('unit') }}" maxlength="20"></label>
            <label>Min (numbers)<input type="number" step="any" name="min_value" value="{{ old('min_value') }}"></label>
            <label>Max (numbers)<input type="number" step="any" name="max_value" value="{{ old('max_value') }}"></label>
            <label>Help text<input name="help_text" value="{{ old('help_text') }}" maxlength="255"></label>
            <label>Order<input type="number" name="sort_order" value="{{ old('sort_order', 100) }}"></label>
            <label><input class="switch" type="checkbox" name="is_required" value="1" @checked(old('is_required'))> Required before the record can be saved</label>
        </div>
        <div class="actions" style="margin-top:12px"><button type="button" class="btn" onclick="this.closest('dialog').close()">Cancel</button><button class="btn btn-primary">Add field</button></div>
    </form>
</dialog>
<script>(function(){var d=document.getElementById('cf-new');if(d&&d.hasAttribute('data-open')&&d.showModal)d.showModal();})();</script>
@endsection
