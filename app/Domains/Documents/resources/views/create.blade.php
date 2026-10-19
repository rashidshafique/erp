@extends('layouts.app')

@section('title', 'New controlled document')
@section('breadcrumb')<a href="{{ route('documents.index') }}">Controlled documents</a> / New @endsection

@section('content')
<div class="page-heading">
    <div><h1>New controlled document</h1><p>The first revision starts as a draft. It is not in force until approvers have signed it off.</p></div>
    <div class="actions"><a class="btn" href="{{ route('documents.index') }}">Cancel</a></div>
</div>
<form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">@csrf
    <div class="panel"><div class="panel-header"><h2>The document</h2></div><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="document_number">Document number *</label><input id="document_number" name="document_number" required maxlength="100" value="{{ old('document_number') }}" placeholder="e.g. QP-07" style="text-transform:uppercase"></div>
        <div class="field"><label for="document_type">Type *</label><select id="document_type" name="document_type">@foreach (\App\Domains\Documents\Models\ControlledDocument::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('document_type', $document->document_type) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field field-full"><label for="title">Title *</label><input id="title" name="title" required maxlength="250" value="{{ old('title') }}"></div>
        <div class="field"><label for="owner_name">Owner</label><input id="owner_name" name="owner_name" maxlength="150" value="{{ old('owner_name') }}"></div>
        <div class="field"><label for="owning_department">Department</label><input id="owning_department" name="owning_department" maxlength="100" value="{{ old('owning_department') }}"></div>
        <div class="field"><label for="review_interval_months">Review every (months)</label><input id="review_interval_months" type="number" name="review_interval_months" min="1" max="120" value="{{ old('review_interval_months', $document->review_interval_months) }}"><div class="help">Leave empty if it needs no periodic review.</div></div>
        <div class="field field-full"><label for="description">What it covers</label><textarea id="description" name="description" rows="3" maxlength="4000">{{ old('description') }}</textarea></div>
    </div></div></div>
    <div class="panel"><div class="panel-header"><h2>First revision</h2></div><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="revision_code">Revision *</label><input id="revision_code" name="revision_code" required maxlength="50" value="{{ old('revision_code', 'A') }}"></div>
        <div class="field"><label for="file">File *</label><input id="file" type="file" name="file" required></div>
        <div class="field field-full"><label for="revision_summary">Summary</label><textarea id="revision_summary" name="revision_summary" rows="2" maxlength="2000">{{ old('revision_summary') }}</textarea></div>
    </div></div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Create draft</button></div>
</form>
@endsection
