@extends('layouts.app')
@section('title', 'Import data')
@section('breadcrumb', 'Import data')
@section('content')
<style>.im-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px; } .im-card { display: block; border: 1px solid var(--border); border-radius: 10px; padding: 12px; background: #fff; color: inherit; text-decoration: none; } .im-card:hover { border-color: var(--accent); } .im-card strong { display: block; margin-bottom: 3px; } .im-card span { font-size: 12.5px; color: var(--muted); }</style>
@php $cls = ['UPLOADED' => 'status-on-hold', 'MAPPED' => 'status-in-progress', 'RUNNING' => 'status-in-progress', 'COMMITTED' => 'status-completed', 'DISCARDED' => 'status-cancelled']; @endphp
<div class="page-heading"><div><h1>Import data</h1><p>Bring in products, patterns and tooling from a spreadsheet: Excel, CSV or a Google Sheet.</p></div></div>
<div class="panel"><div class="panel-header"><h2>What do you want to import?</h2></div><div class="panel-body">
<div class="im-cards">
@foreach ($entities as $k => $e)
    <a class="im-card" href="{{ route('imports.create', ['entity' => $k]) }}"><strong>{{ $e['label'] }}</strong><span>{{ $e['blurb'] }}</span></a>
@endforeach
</div>
<p class="muted small" style="margin-top:10px">Order matters for tooling: import cores before the core boxes that refer to them, and patterns and products before the pattern cavities that link them. Every import shows a preview first and changes nothing until you confirm.</p>
</div></div>
<div class="tab-marker" data-tab-start="Recent imports"></div>
<div class="panel"><div class="panel-header"><h2>Recent imports</h2></div>
@if ($recent->isEmpty())<div class="empty-state">No imports yet.</div>@else
<div class="table-wrap"><table><thead><tr><th>When</th><th>What</th><th>Source</th><th>Result</th><th>Status</th><th>By</th><th></th></tr></thead><tbody>
@foreach ($recent as $r)
<tr><td class="small">{{ $r->created_at->displayShortDateTime() }}</td><td>{{ config('imports.entities.'.$r->entity.'.label') }}</td><td class="small">{{ $r->source_type }} · {{ \Illuminate\Support\Str::limit($r->source_label, 40) }}</td>
<td class="small">@if ($r->counts){{ $r->counts['CREATE'] }} added · {{ $r->counts['UPDATE'] }} updated · {{ $r->counts['ERROR'] }} errors @else — @endif</td>
<td><span class="status {{ $cls[$r->status] ?? '' }}">{{ $r->status }}</span></td><td class="small">{{ $r->created_by_name ?? '—' }}</td>
<td>@if ($r->status !== 'DISCARDED')<a href="{{ route('imports.show', $r) }}">{{ $r->status === 'COMMITTED' ? 'Open' : 'Continue' }} →</a>@endif</td></tr>
@endforeach
</tbody></table></div>@endif
</div>
<div class="tab-marker" data-tab-start="Saved mappings"></div>
<div class="panel"><div class="panel-header"><h2>Saved mappings</h2></div>
@if ($templates->isEmpty())<div class="empty-state">None yet. On the mapping screen, give the mapping a name to reuse it for the next file with the same headings.</div>@else
<div class="table-wrap"><table><thead><tr><th>Name</th><th>For</th><th>Columns</th><th></th></tr></thead><tbody>
@foreach ($templates as $t)<tr><td>{{ $t->name }}</td><td>{{ config('imports.entities.'.$t->entity.'.label') }}</td><td class="small muted">{{ implode(', ', array_slice($t->headers, 0, 8)) }}{{ count($t->headers) > 8 ? '…' : '' }}</td>
<td><form method="POST" action="{{ route('imports.templates.destroy', $t) }}" onsubmit="return confirm('Remove this saved mapping?')">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'name' => $t->name])</form></td></tr>@endforeach
</tbody></table></div>@endif
</div>
@endsection
