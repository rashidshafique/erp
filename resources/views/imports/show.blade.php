@extends('layouts.app')
@section('title', 'Import result')
@section('breadcrumb')<a href="{{ route('imports.index') }}">Import data</a> / {{ $def['label'] }} / Result @endsection
@section('content')
@php $c = $b->counts; $cls = ['CREATE' => 'status-completed', 'UPDATE' => 'status-in-progress', 'UNCHANGED' => 'status-on-hold', 'SKIP' => 'status-on-hold', 'ERROR' => 'status-cancelled']; $bad = collect($b->report)->where('action', 'ERROR'); @endphp
<div class="page-heading"><div><h1>Import finished</h1><p>{{ $def['label'] }} from {{ $b->source_label }}, {{ $b->committed_at?->format('d M Y H:i') }} by {{ $b->created_by_name ?? '—' }}.</p></div>
<div class="actions"><a class="btn" href="{{ route('imports.report', $b) }}">Download the full report (CSV)</a> <a class="btn btn-primary" href="{{ route('imports.create', ['entity' => $b->entity]) }}">Import another file</a></div></div>
<div class="panel"><div class="panel-body"><strong>{{ $c['CREATE'] }}</strong> added · <strong>{{ $c['UPDATE'] }}</strong> updated · {{ $c['UNCHANGED'] }} unchanged · {{ $c['SKIP'] }} skipped · <span style="{{ $c['ERROR'] ? 'color:#b00020' : '' }}">{{ $c['ERROR'] }} with errors</span> (of {{ $c['TOTAL'] }} rows)</div></div>
@if ($bad->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Rows that were not imported</h2></div><div class="table-wrap"><table><thead><tr><th>Row</th><th>{{ $def['columns'][1] ?? 'Name' }}</th><th>Why</th></tr></thead><tbody>
@foreach ($bad->take(200) as $r)<tr><td>{{ $r['n'] }}</td><td>{{ $r['name'] ?? '—' }}</td><td style="white-space:normal;color:#b00020">{{ $r['message'] }}</td></tr>@endforeach
</tbody></table></div><div class="panel-body muted small">Fix these in your sheet and import it again. Rows already imported are matched and not duplicated.</div></div>
@endif
@include('audit._history', ['type' => 'ImportBatch', 'id' => $b->id])
@endsection
