@extends('layouts.app')
@section('title', 'Check the import')
@section('breadcrumb')<a href="{{ route('imports.index') }}">Import data</a> / {{ $def['label'] }} / Check @endsection
@section('content')
<style>.cnt { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; } .cnt a { border: 1px solid var(--border); border-radius: 10px; padding: 8px 14px; background: #fff; color: inherit; text-decoration: none; min-width: 110px; } .cnt a.on { border-color: var(--accent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 22%, transparent); } .cnt b { font-size: 20px; display: block; } .pv td { vertical-align: top; white-space: normal; } .err { color: #b00020; } .chg { font-size: 12.5px; }</style>
@php
    $cls = ['CREATE' => 'status-completed', 'UPDATE' => 'status-in-progress', 'UNCHANGED' => 'status-on-hold', 'SKIP' => 'status-on-hold', 'ERROR' => 'status-cancelled'];
    $lab = ['CREATE' => 'Will add', 'UPDATE' => 'Will update', 'UNCHANGED' => 'No change', 'SKIP' => 'Skipped', 'ERROR' => 'Errors'];
    $doable = $counts['CREATE'] + $counts['UPDATE'];
@endphp
<div class="page-heading"><div><h1>Check before importing</h1><p>{{ $b->source_label }}. This is a dry run: nothing has been saved.</p></div>
<div class="actions"><a class="btn" href="{{ route('imports.map', $b) }}">Change the mapping</a></div></div>
@if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
<div class="cnt">
    <a href="{{ route('imports.preview', $b) }}" class="{{ $show === 'ALL' ? 'on' : '' }}"><b>{{ $counts['TOTAL'] }}</b>All rows</a>
    @foreach ($lab as $k => $l)<a href="{{ route('imports.preview', [$b, 'show' => $k]) }}" class="{{ $show === $k ? 'on' : '' }}"><b>{{ $counts[$k] }}</b>{{ $l }}</a>@endforeach
</div>

@if ($unknown)
<div class="panel"><div class="panel-header"><h2>Values we do not recognise</h2></div><div class="panel-body">
<p class="muted">Say what each one means. Rows with these values stay as errors until you do.</p>
<form method="POST" action="{{ route('imports.values', $b) }}">@csrf
<table><thead><tr><th>Field</th><th>Your value</th><th>Rows</th><th>Means</th></tr></thead><tbody>
@foreach ($unknown as $field => $vals)
@php $f = $def['fields'][$field]; @endphp
@foreach ($vals as $raw => $n)
<tr><td>{{ $f['label'] }}</td><td><strong>{{ $raw }}</strong></td><td>{{ $n }}</td>
<td><select name="value_map[{{ $field }}][{{ base64_encode((string) $raw) }}]"><option value="">— choose —</option>
@if ($f['type'] === 'enum')@foreach ($f['enum'] as $ev)<option value="{{ $ev }}">{{ $ev }}</option>@endforeach<option value="__SKIP__">Ignore this value (leave the field blank)</option>
@else<option value="1">Yes</option><option value="0">No</option>@endif</select></td></tr>
@endforeach
@endforeach
</tbody></table>
<p><button class="btn btn-primary" type="submit">Apply and check again</button></p></form>
</div></div>
@endif

<div class="panel"><div class="panel-header"><h2>Rows</h2><span class="muted small">{{ $total }} shown</span></div>
@if ($rows === [])<div class="empty-state">No rows in this view.</div>@else
<div class="table-wrap"><table class="pv"><thead><tr><th>Row</th><th>Result</th>@foreach ($def['columns'] ?? ['Code', 'Name'] as $heading)<th>{{ $heading }}</th>@endforeach<th>Details</th></tr></thead><tbody>
@foreach ($rows as $r)
<tr><td class="muted">{{ $r['n'] }}</td><td><span class="status {{ $cls[$r['action']] }}">{{ $lab[$r['action']] }}</span></td>
<td>{{ $r['code'] ?? ($r['action'] === 'CREATE' ? 'auto' : '—') }}</td><td>{{ $r['name'] ?? '—' }}</td>
<td>@foreach ($r['errors'] as $e)<div class="err">{{ $e }}</div>@endforeach @foreach ($r['notes'] as $e)<div class="muted small">{{ $e }}</div>@endforeach
@foreach ($r['changes'] as $field => $c)<div class="chg"><strong>{{ $def['fields'][$field]['label'] ?? $field }}</strong>: {{ $c[0] }} → {{ $c[1] }}</div>@endforeach</td></tr>
@endforeach
</tbody></table></div>
@if ($total > $per)
<div class="panel-body" style="display:flex;gap:8px;align-items:center">
@if ($page > 1)<a class="btn" href="{{ route('imports.preview', [$b, 'show' => $show, 'page' => $page - 1]) }}">← Earlier</a>@endif
<span class="muted small">Rows {{ ($page - 1) * $per + 1 }}–{{ min($total, $page * $per) }} of {{ $total }}</span>
@if ($page * $per < $total)<a class="btn" href="{{ route('imports.preview', [$b, 'show' => $show, 'page' => $page + 1]) }}">Later →</a>@endif
</div>@endif
@endif</div>

<div class="panel"><div class="panel-body">
@if ($doable === 0)<p>Nothing would be added or changed. Fix the errors or change the mapping.</p>
@else
<p><strong>{{ $counts['CREATE'] }}</strong> will be added and <strong>{{ $counts['UPDATE'] }}</strong> updated.@if ($counts['ERROR'])
 <span class="err">{{ $counts['ERROR'] }} row(s) with errors will be left out</span>{{ ! empty($b->options['stop_on_error']) ? ', and because you chose to stop on errors nothing will be imported until they are fixed' : '' }}.@endif</p>
<form method="POST" action="{{ route('imports.commit', $b) }}" onsubmit="this.querySelector('button').disabled=true">@csrf<button class="btn btn-primary" type="submit">Import now</button></form>
@endif
</div></div>
@endsection
