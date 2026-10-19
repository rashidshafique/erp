@extends('layouts.app')

@section('title', $capa->code)
@section('breadcrumb')<a href="{{ route('capa.index') }}">Corrective actions</a> / {{ $capa->code }} @endsection

@section('content')
@php $open = $capa->isOpen(); $editable = in_array($capa->status, ['OPEN', 'IN_PROGRESS'], true); @endphp
<div class="page-heading">
    <div>
        <h1>{{ $capa->title }}</h1>
        <p><span class="mono">{{ $capa->code }}</span> · {{ \App\Domains\Capa\Models\CapaAction::SOURCES[$capa->source_type] ?? $capa->source_type }}@if ($capa->source_label) · @if ($ncr)<a href="{{ route('ncrs.show', $ncr) }}">{{ $capa->source_label }}</a>@else{{ $capa->source_label }}@endif @endif · {{ \App\Domains\Capa\Models\CapaAction::SEVERITIES[$capa->severity] ?? $capa->severity }} · @include('capa::_status', ['capa' => $capa])</p>
    </div>
    <div class="actions"><a class="btn" href="{{ route('capa.index') }}">All actions</a></div>
</div>

@if ($capa->verification_log)<div class="alert alert-warning"><strong>Earlier effectiveness checks</strong><div style="white-space:pre-line">{{ $capa->verification_log }}</div></div>@endif
@if ($capa->status === 'CLOSED')<div class="alert alert-success">Closed {{ $capa->closed_at?->displayDate() }}. Checked {{ $capa->verified_on?->displayDate() }} by {{ $capa->verified_by }}: effective. {{ $capa->verification_notes }}</div>
@elseif ($capa->status === 'CANCELLED')<div class="alert alert-warning">Cancelled: {{ $capa->cancel_reason }}</div>@endif

<div class="panel"><div class="panel-header"><h2>Investigation and plan</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('capa.update', $capa) }}">@csrf @method('PUT')
        <div class="form-grid">
            <div class="field field-full"><label for="title">What went wrong</label><input id="title" name="title" maxlength="200" required value="{{ old('title', $capa->title) }}" @disabled(! $editable)></div>
            <div class="field field-full"><label for="description">Details</label><textarea id="description" name="description" rows="3" maxlength="4000" @disabled(! $editable)>{{ old('description', $capa->description) }}</textarea></div>
            <div class="field"><label for="severity">Severity</label><select id="severity" name="severity" @disabled(! $editable)>@foreach (\App\Domains\Capa\Models\CapaAction::SEVERITIES as $k => $l)<option value="{{ $k }}" @selected(old('severity', $capa->severity) === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="owner">Owner</label><input id="owner" name="owner" maxlength="150" value="{{ old('owner', $capa->owner) }}" @disabled(! $editable)></div>
            <div class="field"><label for="due_on">Action due</label><input id="due_on" type="date" name="due_on" value="{{ old('due_on', $capa->due_on?->toDateString()) }}" @disabled(! $editable)></div>
            <div class="field field-full"><label for="containment_action">Containment (what was done at once)</label><textarea id="containment_action" name="containment_action" rows="2" maxlength="2000" @disabled(! $editable)>{{ old('containment_action', $capa->containment_action) }}</textarea></div>
            <div class="field field-full"><label for="root_cause">Root cause</label><textarea id="root_cause" name="root_cause" rows="3" maxlength="2000" @disabled(! $editable)>{{ old('root_cause', $capa->root_cause) }}</textarea></div>
            <div class="field field-full"><label for="corrective_action">Corrective action (removes the cause)</label><textarea id="corrective_action" name="corrective_action" rows="3" maxlength="2000" @disabled(! $editable)>{{ old('corrective_action', $capa->corrective_action) }}</textarea></div>
            <div class="field field-full"><label for="preventive_action">Preventive action (stops it happening elsewhere)</label><textarea id="preventive_action" name="preventive_action" rows="2" maxlength="2000" @disabled(! $editable)>{{ old('preventive_action', $capa->preventive_action) }}</textarea></div>
        </div>
        @if ($editable)<button class="btn btn-primary" type="submit">Save</button> <span class="muted small">The action moves to "in progress" once the root cause, corrective action, owner and due date are all filled in.</span>@endif
    </form>
</div></div>

@if ($capa->status === 'IN_PROGRESS')
<div class="panel"><div class="panel-header"><h2>The action has been carried out</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('capa.complete', $capa) }}">@csrf
        <div class="form-grid">
            <div class="field"><label for="completed_on">Done on *</label><input id="completed_on" type="date" name="completed_on" required max="{{ today()->toDateString() }}" value="{{ old('completed_on', today()->toDateString()) }}"></div>
            <div class="field"><label for="verification_due_on">Check that it worked on *</label><input id="verification_due_on" type="date" name="verification_due_on" required value="{{ old('verification_due_on', today()->addDays(30)->toDateString()) }}"><div class="help">Long enough for the problem to show up again if the action did not work: a month at least.</div></div>
            <div class="field field-full"><label for="implementation_notes">What was done</label><textarea id="implementation_notes" name="implementation_notes" rows="2" maxlength="2000">{{ old('implementation_notes') }}</textarea></div>
        </div>
        <button class="btn btn-primary" type="submit">Record as done</button>
    </form>
</div></div>
@elseif ($capa->status === 'AWAITING_VERIFICATION')
<div class="panel"><div class="panel-header"><h2>Check that the action worked</h2></div><div class="panel-body">
    <p class="muted small" style="margin-top:0">Done {{ $capa->completed_on?->displayDate() }}. {{ $capa->implementation_notes }} The check is due {{ $capa->verification_due_on?->displayDate() }}: look at the records since then, for example further NCRs, rejects or complaints of the same kind.</p>
    <form method="POST" action="{{ route('capa.verify', $capa) }}">@csrf
        <div class="form-grid">
            <div class="field"><label for="verification_result">Result *</label><select id="verification_result" name="verification_result"><option value="EFFECTIVE">Effective: it has not happened again</option><option value="NOT_EFFECTIVE" @selected(old('verification_result') === 'NOT_EFFECTIVE')>Not effective: it has</option></select></div>
            <div class="field"><label for="verified_by">Checked by *</label><input id="verified_by" name="verified_by" required maxlength="150" value="{{ old('verified_by') }}"></div>
            <div class="field"><label for="verified_on">Checked on *</label><input id="verified_on" type="date" name="verified_on" required max="{{ today()->toDateString() }}" value="{{ old('verified_on', today()->toDateString()) }}"></div>
            <div class="field field-full"><label for="verification_notes">What was looked at</label><textarea id="verification_notes" name="verification_notes" rows="2" maxlength="2000">{{ old('verification_notes') }}</textarea></div>
        </div>
        <button class="btn btn-primary" type="submit">Record the check</button>
    </form>
</div></div>
@endif

@if ($open)
<div class="panel"><div class="panel-header"><h2>Cancel</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('capa.cancel', $capa) }}" class="form-grid" style="align-items:end" onsubmit="return confirm('Cancel {{ $capa->code }}?')">@csrf
        <div class="field field-full"><label for="cancel_reason">Why is it being cancelled? (raised in error, duplicate, no longer relevant)</label><input id="cancel_reason" name="cancel_reason" required maxlength="1000"></div>
        <div><button class="btn" type="submit">Cancel action</button></div>
    </form>
</div></div>
@endif

@include('attachments._panel', ['type' => 'capa', 'id' => $capa->id])
@include('audit._history', ['type' => 'CapaAction', 'id' => $capa->id])
@endsection
