@extends('layouts.app')

@php $isNew = ! $core->exists; @endphp
@section('title', $isNew ? 'New core' : 'Edit '.$core->code)
@section('breadcrumb')<a href="{{ route('cores.index') }}">Cores</a> / {{ $isNew ? 'New' : $core->code }} @endsection

@section('content')
<div class="page-heading">
    <div><h1>{{ $isNew ? 'New core' : 'Edit '.$core->code }}</h1><p>What the core is. Which product needs it and which box makes it are set on their own pages.</p></div>
</div>
<form method="POST" action="{{ $isNew ? route('cores.store') : route('cores.update', $core) }}">
    @csrf @if (! $isNew) @method('PUT') @endif
    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="name">Name *</label><input id="name" name="name" required maxlength="255" value="{{ old('name', $core->name) }}"></div>
        <div class="field"><label for="code">Code</label><input id="code" name="code" maxlength="40" value="{{ old('code', $core->code) }}"><div class="help">Leave empty to number it automatically.</div></div>
        <div class="field"><label for="core_type">Type *</label><select id="core_type" name="core_type" required>@foreach (\App\Models\CoreDefinition::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('core_type', $core->core_type) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="process_type">Core process</label><input id="process_type" name="process_type" maxlength="30" list="core-processes" value="{{ old('process_type', $core->process_type) }}"><datalist id="core-processes">@foreach (['Cold box', 'Hot box', 'Shell', 'No-bake', 'CO2', 'Oil sand'] as $p)<option value="{{ $p }}">@endforeach</datalist></div>
        <div class="field"><label for="nominal_weight_kg">Weight (kg)</label><input id="nominal_weight_kg" type="number" name="nominal_weight_kg" min="0" step="any" value="{{ old('nominal_weight_kg', $core->nominal_weight_kg) }}"></div>
        <div class="field"><label for="status">Status *</label><select id="status" name="status" required>@foreach (['ACTIVE', 'INACTIVE', 'RETIRED'] as $s)<option value="{{ $s }}" @selected(old('status', $core->status) === $s)>{{ $s }}</option>@endforeach</select></div>
        <div class="field field-full"><label for="description">Description</label><textarea id="description" name="description" rows="2" maxlength="2000">{{ old('description', $core->description) }}</textarea></div>
        <div class="field field-full"><label for="engineering_notes">Engineering notes</label><textarea id="engineering_notes" name="engineering_notes" rows="2" maxlength="4000">{{ old('engineering_notes', $core->engineering_notes) }}</textarea></div>
    </div></div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save core</button><a class="btn" href="{{ $isNew ? route('cores.index') : route('cores.show', $core) }}">Cancel</a></div>
</form>
@endsection
