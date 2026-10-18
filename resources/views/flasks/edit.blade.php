@extends('layouts.app')

@section('title', 'Edit '.$flask->code)
@section('breadcrumb')<a href="{{ route('flasks.index') }}">Flasks</a> / <a href="{{ route('flasks.show', $flask) }}">{{ $flask->code }}</a> / Edit @endsection

@section('content')
<div class="page-heading">
    <div><h1>Edit <span class="mono">{{ $flask->code }}</span></h1><p>Changes are kept in the flask's history.</p></div>
    <div class="actions"><a class="btn" href="{{ route('flasks.show', $flask) }}">Back to flask</a></div>
</div>
<form method="POST" action="{{ route('flasks.update', $flask) }}">
    @csrf @method('PUT')
    <div class="panel"><div class="panel-body">
        <div class="form-grid">
            <div class="field"><label for="mark_code">Stamped mark *</label><input id="mark_code" name="mark_code" required minlength="3" maxlength="20" pattern="[A-Za-z0-9]+" value="{{ old('mark_code', $flask->ensureMark()) }}" style="font-family:ui-monospace,Menlo,monospace;text-transform:uppercase"><div class="help">The number stamped on the flask. Unique across all tools.</div></div>
            <div class="field"><label for="flask_size_id">Size *</label><select id="flask_size_id" name="flask_size_id" required>@foreach ($sizes as $s)<option value="{{ $s->id }}" @selected(old('flask_size_id', $flask->flask_size_id) === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
            <div class="field"><label for="position">Position *</label><select id="position" name="position" required>@foreach (\App\Models\Flask::POSITIONS as $k => $l)<option value="{{ $k }}" @selected(old('position', $flask->position) === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="construction">Construction</label><select id="construction" name="construction"><option value="">—</option>@foreach (\App\Models\Flask::CONSTRUCTIONS as $k => $l)<option value="{{ $k }}" @selected(old('construction', $flask->construction) === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="material">Material</label><input id="material" name="material" maxlength="50" value="{{ old('material', $flask->material) }}"></div>
            <div class="field"><label for="weight_kg">Empty weight (kg)</label><input id="weight_kg" type="number" name="weight_kg" min="0.1" step="0.1" value="{{ old('weight_kg', $flask->weight_kg) }}"></div>
            <div class="field"><label for="location">Location</label><input id="location" name="location" maxlength="100" value="{{ old('location', $flask->location) }}"></div>
            <div class="field"><label for="partner_flask_id">Fixed partner</label><select id="partner_flask_id" name="partner_flask_id" data-search-select><option value="">— none —</option>@foreach ($partners as $p)<option value="{{ $p->id }}" data-sub="{{ \App\Models\Flask::POSITIONS[$p->position] ?? '' }}" @selected(old('partner_flask_id', $flask->partner_flask_id) === $p->id)>{{ $p->label() }}</option>@endforeach</select><div class="help">For a cope and drag that always go together. Used when the flask rules ask for fixed pairs.</div></div>
            <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes', $flask->notes) }}</textarea></div>
        </div>
    </div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save flask</button><a class="btn" href="{{ route('flasks.show', $flask) }}">Cancel</a></div>
</form>
@include('partials.search-select')
@endsection
