@extends('layouts.app')
@section('title', 'Documents')
@section('content')
<div class="page-heading"><div><h1>Documents</h1><p>How files attached to records are kept.</p></div></div>
<form method="POST" action="{{ route('settings.documents.update') }}">@csrf @method('PUT')
<div class="panel"><div class="panel-body">
    <label style="display:flex;gap:8px;align-items:flex-start;margin-bottom:16px"><input type="checkbox" name="related" value="1" style="width:auto;min-height:0;margin-top:3px" @checked(old('related', $related))>
        <span><strong>Show files of related records</strong><br><span class="muted small">A sales order lists its products' and patterns' drawings, a dispatch lists its lots' certificates, and so on. Each person only sees files they are allowed to see.</span></span></label>
    <label style="display:flex;gap:8px;align-items:flex-start"><input type="checkbox" name="versioning" value="1" style="width:auto;min-height:0;margin-top:3px" @checked(old('versioning', $enabled))>
        <span><strong>Keep versions of files</strong><br><span class="muted small">A new version of a file replaces the old one on the record, and the old one stays in its history. Leave this off to just add and remove files.</span></span></label>
    <fieldset style="margin-top:16px"><legend>Categories that keep versions</legend>
        <div class="rule-grid">
            @foreach ($categories as $key => $label)
                <label><input type="checkbox" name="categories[]" value="{{ $key }}" @checked(in_array($key, old('categories', $selected), true))> {{ $label }}</label>
            @endforeach
        </div>
        <p class="muted small">Photos and other loose files usually do not need versions.</p>
    </fieldset>
</div></div>
<p><button class="btn btn-primary" type="submit">Save</button></p></form>
@endsection
