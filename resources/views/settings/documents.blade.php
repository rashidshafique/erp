@extends('layouts.app')
@section('title', 'Documents')
@section('content')
<div class="page-heading"><div><h1>Documents</h1><p>How files attached to records are kept.</p></div></div>
<form method="POST" action="{{ route('settings.documents.update') }}">@csrf @method('PUT')
<div class="panel"><div class="panel-body">
    <label style="display:flex;gap:8px;align-items:flex-start;margin-bottom:16px"><input type="checkbox" name="related" value="1" style="width:auto;min-height:0;margin-top:3px" @checked(old('related', $related))>
        <span><strong>Show files of related records</strong><br><span class="muted small">E.g. an order shows its products' drawings. Permissions still apply.</span></span></label>
    <label style="display:flex;gap:8px;align-items:flex-start"><input type="checkbox" name="versioning" value="1" style="width:auto;min-height:0;margin-top:3px" @checked(old('versioning', $enabled))>
        <span><strong>Keep versions of files</strong><br><span class="muted small">A new version replaces the old, which stays in history.</span></span></label>
    <fieldset style="margin-top:16px"><legend>Categories that keep versions</legend>
        <div class="rule-grid">
            @foreach ($categories as $key => $label)
                <label><input type="checkbox" name="categories[]" value="{{ $key }}" @checked(in_array($key, old('categories', $selected), true))> {{ $label }}</label>
            @endforeach
        </div>
        <p class="muted small">Photos rarely need versions.</p>
    </fieldset>
</div></div>
<p><button class="btn btn-primary" type="submit">Save</button></p></form>
@endsection
