@extends('layouts.app')
@section('title', 'New enquiry')
@section('breadcrumb')<a href="{{ route('enquiries.index') }}">Enquiries</a> / New @endsection
@section('content')
<div class="page-heading"><div><h1>New enquiry</h1><p>Who is asking, and what kind of request is it. You add the parts on the next screen.</p></div></div>
<form method="POST" action="{{ route('enquiries.store') }}">@csrf
<div class="panel"><div class="panel-body"><div class="form-grid" data-progressive="3">
    <div class="field"><label for="customer_id">Customer</label><select id="customer_id" name="customer_id" required><option value="">— choose —</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id') === $c->id)>{{ $c->code }} — {{ $c->name }}</option>@endforeach</select></div>
    <div class="field"><label for="title">Title</label><input id="title" name="title" value="{{ old('title') }}" required placeholder="e.g. Pump housing, 200 a year"></div>
    <div class="field field-full"><label for="source_type">What are they giving us?</label><select id="source_type" name="source_type">@foreach (\App\Domains\Enquiry\Models\Enquiry::SOURCES as $k => $l)<option value="{{ $k }}" @selected(old('source_type', 'DRAWING') === $k)>{{ $l }}</option>@endforeach</select>
        <div class="help">Repeat: a part already in the system. Drawing or CAD: new work from a model. Customer tool: they send a pattern or core box. Transfer: moving from another foundry. Change: a revision of a part we make. Concept: no drawing yet.</div></div>
    <div class="field"><label for="received_on">Received</label><input id="received_on" type="date" name="received_on" value="{{ old('received_on', now()->toDateString()) }}"></div>
    <div class="field"><label for="due_on">Quote needed by</label><input id="due_on" type="date" name="due_on" value="{{ old('due_on') }}"></div>
    <div class="field"><label for="customer_reference">Their reference</label><input id="customer_reference" name="customer_reference" value="{{ old('customer_reference') }}"></div>
    <div class="field"><label for="contact_name">Contact</label><input id="contact_name" name="contact_name" value="{{ old('contact_name') }}"></div>
    <div class="field field-full"><label for="summary">What they said</label><textarea id="summary" name="summary" rows="3">{{ old('summary') }}</textarea></div>
</div></div></div>
<p><button class="btn btn-primary" type="submit">Create enquiry</button> <a class="btn" href="{{ route('enquiries.index') }}">Cancel</a></p>
</form>
@endsection
