@extends('layouts.app')
@section('title', 'Importing')
@section('breadcrumb')<a href="{{ route('imports.index') }}">Import data</a> / {{ $def['label'] }} / Importing @endsection
@section('content')
@php $total = max(1, (int) ($b->counts['TOTAL'] ?? 1)); $done = (int) $b->progress; $pct = min(100, round($done / $total * 100)); @endphp
<div class="page-heading"><div><h1>Importing {{ strtolower($def['label']) }}…</h1><p>Large files are saved in steps. Keep this page open; it carries on by itself.</p></div></div>
<div class="panel"><div class="panel-body">
    <div style="background:#e6ebe8;border-radius:999px;height:14px;overflow:hidden"><div style="width:{{ $pct }}%;height:100%;background:var(--accent)"></div></div>
    <p><strong>{{ \App\Support\Formats::number($done) }}</strong> of {{ \App\Support\Formats::number($total) }} rows handled · {{ $b->counts['CREATE'] }} added · {{ $b->counts['UPDATE'] }} updated · {{ $b->counts['ERROR'] }} errors</p>
    <form method="POST" action="{{ route('imports.commit', $b) }}" id="goOn">@csrf<button class="btn btn-primary" type="submit">Continue</button></form>
    <p class="muted small">If this page was closed or the connection dropped, open it again from Import data → Recent imports and press Continue. Rows already saved are not repeated.</p>
</div></div>
<script>setTimeout(function () { var f = document.getElementById('goOn'); f.querySelector('button').disabled = true; f.submit(); }, 400);</script>
@endsection
