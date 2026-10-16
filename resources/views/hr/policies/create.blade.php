@extends('layouts.app')
@section('title', 'New policy')
@section('breadcrumb')<a href="{{ route('hr.policies.index') }}">HR policies</a> / New @endsection
@section('content')
<div class="page-heading"><div><h1>New policy</h1><p>Saved as a draft until you publish it.</p></div></div>
<form method="POST" action="{{ route('hr.policies.store') }}">@csrf
<div class="panel"><div class="panel-body"><div class="form-grid">
    @include('hr._in', ['name' => 'title', 'label' => 'Title', 'val' => '', 'required' => true])
    @include('hr._sel', ['name' => 'category', 'label' => 'Category', 'items' => config('hr.policy_categories'), 'sel' => 'GENERAL', 'blank' => false, 'required' => true])
    @include('hr._in', ['name' => 'effective_on', 'label' => 'Effective from', 'type' => 'date', 'val' => ''])
    <div class="field"><label><input type="checkbox" name="requires_ack" value="1" checked> Everyone must acknowledge it</label></div>
    <div class="field field-full"><label for="f_body">Policy text</label><textarea id="f_body" name="body" rows="14">{{ old('body') }}</textarea></div>
</div></div></div>
<p><button class="btn btn-primary">Save draft</button> <a class="btn" href="{{ route('hr.policies.index') }}">Cancel</a></p></form>
@endsection
