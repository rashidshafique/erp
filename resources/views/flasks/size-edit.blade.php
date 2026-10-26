@extends('layouts.app')

@section('title', 'Edit '.$size->name)
@section('breadcrumb', 'Flasks / Sizes / '.$size->name)

@section('content')
<div class="page-heading">
    <div><h1>Flask size {{ $size->name }}</h1><p>Flasks of one size are interchangeable: the same inside size and pin centres.</p></div>
    <div class="actions"><a class="btn" href="{{ route('flasks.index') }}">Flask board</a></div>
</div>
<form method="POST" action="{{ route('flask-sizes.update', $size) }}">
    @csrf @method('PUT')
    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="name">Name *</label><input id="name" name="name" required maxlength="100" value="{{ old('name', $size->name) }}"></div>
        @foreach (['inside_length_mm' => 'Inside length (mm)', 'inside_width_mm' => 'Inside width (mm)', 'cope_depth_mm' => 'Cope depth (mm)', 'drag_depth_mm' => 'Drag depth (mm)', 'pin_centres_mm' => 'Pin centres (mm)'] as $field => $label)
            <div class="field"><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" type="number" name="{{ $field }}" min="1" step="any" value="{{ old($field, $size->{$field} !== null ? rtrim(rtrim((string) $size->{$field}, '0'), '.') : '') }}"></div>
        @endforeach
        <div class="field"><label for="status">Status *</label><select id="status" name="status">@foreach (['ACTIVE', 'INACTIVE'] as $st)<option @selected(old('status', $size->status) === $st)>{{ $st }}</option>@endforeach</select></div>
        <div class="field field-full"><label><input type="hidden" name="is_block" value="0"><input type="checkbox" name="is_block" value="1" @checked(old('is_block', $size->is_block))> Block size, for flaskless moulds</label><div class="help">A block is what a flaskless line makes: it has a size but no flasks, so no flasks are counted or picked for it.</div></div>
        <div class="field"><label for="sets_owned">Sets owned (count only)</label><input id="sets_owned" type="number" name="sets_owned" min="0" step="1" value="{{ old('sets_owned', $size->sets_owned) }}"><div class="help">For a size you count instead of registering each flask: e.g. 40 sets. Moulds take a set each and give it back when freed. Ignored once flasks of this size are registered.</div></div>
        <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes', $size->notes) }}</textarea></div>
    </div></div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save size</button><a class="btn" href="{{ route('flasks.index') }}">Cancel</a></div>
</form>
@endsection
