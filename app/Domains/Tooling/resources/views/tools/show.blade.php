@extends('layouts.app')
@section('title', $tool->code)
@section('content')
<div class="page-heading"><div><h1>{{ $tool->name }}</h1><p>{{ $label }} · {{ $tool->code }}</p></div><a class="btn" href="{{ route('labels.show', [$type, $tool->id]) }}" target="_blank">Print label</a></div>
<div class="tab-marker" data-tab-start="Tool"></div>
<div class="panel"><div class="panel-header"><h2>{{ $label }}</h2></div><div class="panel-body">
@include('attachments._cover', ['type' => $type, 'id' => $tool->id])
@include('tooling::tools._mark', ['tool' => $tool, 'type' => $type])
<div class="muted small" style="margin-top:8px">Barcode</div>{!! \App\Support\Code128::svg($tool->code, 44, 2) !!}
</div></div>
<div class="panel"><div class="panel-header"><h2>Maintenance records</h2></div><div class="table-wrap"><table><thead><tr><th>Record</th><th>Activity</th><th>Status</th><th>Reported</th></tr></thead><tbody>
@forelse($records as $r)<tr><td><a href="{{ route('tooling-maintenance.show', $r) }}">{{ $r->record_number }}</a></td><td>{{ str_replace('_',' ',$r->activity_type) }}</td><td>{{ $r->status }}</td><td>{{ $r->reported_at?->displayDate() }}</td></tr>
@empty<tr><td colspan="4" class="muted">No maintenance recorded.</td></tr>@endforelse</tbody></table></div></div>
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('attachments._panel', ['type' => $type, 'id' => $tool->id])
@include('audit._history', ['type' => class_basename($tool), 'id' => $tool->id])
@endsection
