@extends('layouts.app')
@section('title', 'Import punches')
@section('breadcrumb', 'Import punches')
@section('content')
<div class="page-heading"><div><h1>Import punches</h1><p>Load a log from a fingerprint, card or face terminal.</p></div></div>
@include('hr.attendance._tabs')
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@isset($result)
<div class="panel"><div class="panel-header"><h2>Result</h2></div><div class="panel-body">
<p><strong>{{ $result['saved'] }}</strong> day(s) saved from {{ $result['punches'] }} punches.</p>
@if ($result['unknown'])<p class="alert alert-error">Not an employee code: {{ implode(', ', $result['unknown']) }}</p>@endif
@foreach (array_merge($result['problems'], $result['bad']) as $m)<p class="small muted">{{ $m }}</p>@endforeach
<p><a class="btn" href="{{ route('hr.attendance') }}">Open the daily sheet</a> <a class="btn" href="{{ route('hr.attendance.exceptions') }}">Exceptions</a></p></div></div>
@endisset
<div class="panel"><div class="panel-body">
<p class="muted">One punch per line: <code>EMP-00012, 2026-10-13 08:04</code> (or code, date, time in three columns). A heading line is fine. The first punch of a day is the check-in and the last is the check-out. A punch just after a night shift ends goes to the shift that started the evening before. Importing again replaces the times for those days.</p>
<form method="POST" action="{{ route('hr.attendance.import.run') }}" enctype="multipart/form-data">@csrf<div class="form-grid">
    <div class="field"><label for="file">CSV or text file</label><input id="file" type="file" name="file" accept=".csv,.txt"></div>
    <div class="field field-full"><label for="pasted">…or paste the log</label><textarea id="pasted" name="pasted" rows="8" placeholder="EMP-00001, 2026-10-13 07:58&#10;EMP-00001, 2026-10-13 17:31"></textarea></div>
</div><p><button class="btn btn-primary">Import</button></p></form></div></div>
@endsection
