@extends('layouts.app')
@section('title', 'Import '.$def['label'])
@section('breadcrumb')<a href="{{ route('imports.index') }}">Import data</a> / {{ $def['label'] }} @endsection
@section('content')
<style>.src-tabs { display: flex; gap: 6px; margin-bottom: 12px; flex-wrap: wrap; } .src-tabs label { border: 1px solid var(--border); border-radius: 999px; padding: 6px 14px; cursor: pointer; background: #fff; } .src-tabs input { position: absolute; opacity: 0; pointer-events: none; } .src-tabs label:focus-within { outline: 2px solid var(--accent); } .src-tabs label.on { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; } .src { display: none; } .src.on { display: block; } textarea.paste { width: 100%; min-height: 180px; font-family: ui-monospace, monospace; font-size: 12px; }</style>
<div class="page-heading"><div><h1>Import {{ strtolower($def['label']) }}</h1><p>{{ $def['blurb'] }}</p></div>
<div class="actions"><a class="btn" href="{{ route('imports.template', $entity) }}">Download a blank template (CSV)</a></div></div>
<form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" id="impForm">@csrf
<input type="hidden" name="entity" value="{{ $entity }}">
<div class="panel"><div class="panel-header"><h2>Where is the data?</h2></div><div class="panel-body">
    <div class="src-tabs">
        <label><input type="radio" name="source" value="FILE" @checked(old('source', 'FILE') === 'FILE')> Excel or CSV file</label>
        <label><input type="radio" name="source" value="GOOGLE" @checked(old('source') === 'GOOGLE')> Google Sheets link</label>
        <label><input type="radio" name="source" value="PASTE" @checked(old('source') === 'PASTE')> Paste cells</label>
    </div>
    <div class="src" data-src="FILE"><div class="field"><label for="file">File (.xlsx or .csv, up to {{ round($maxKb / 1024) }} MB)</label><input id="file" type="file" name="file" accept=".csv,.tsv,.txt,.xlsx"><div class="help">The first sheet is read; you can choose another on the next screen. Old .xls files: open them in Excel and save as .xlsx.</div></div></div>
    <div class="src" data-src="GOOGLE"><div class="field"><label for="google_url">Google Sheets address</label><input id="google_url" name="google_url" value="{{ old('google_url') }}" placeholder="https://docs.google.com/spreadsheets/d/…/edit#gid=0">
        <div class="help">The sheet must be shared as <strong>Anyone with the link → Viewer</strong>. To import a different tab, open that tab first and copy the address from the browser, so it ends with its own gid. A private sheet: use File → Download → CSV in Google Sheets, or the paste option.</div></div></div>
    <div class="src" data-src="PASTE"><div class="field"><label for="pasted">Paste cells</label><textarea id="pasted" class="paste" name="pasted" placeholder="Select the cells in Google Sheets or Excel, including the heading row, copy and paste here.">{{ old('pasted') }}</textarea><div class="help">Include the heading row. Tabs between columns are what a spreadsheet copies, and are read automatically.</div></div></div>
    <p><button class="btn btn-primary" type="submit">Read the data</button> <a class="btn" href="{{ route('imports.index') }}">Cancel</a></p>
</div></div>
</form>
<div class="panel"><div class="panel-header"><h2>Fields you can fill</h2></div><div class="table-wrap"><table><thead><tr><th>Field</th><th>Kind</th><th>Notes</th></tr></thead><tbody>
@foreach ($def['fields'] as $k => $f)<tr><td><strong>{{ $f['label'] }}</strong>{!! ! empty($f['required']) ? ' <span class="muted small">required for new records</span>' : '' !!}</td><td class="small">{{ ['string' => 'Text', 'text' => 'Long text', 'decimal' => 'Number', 'int' => 'Whole number', 'bool' => 'Yes / no', 'date' => 'Date', 'enum' => 'One of: '.implode(', ', $f['enum'] ?? []), 'ref' => 'Matched to an existing record', 'mark' => 'Letters and digits'][$f['type']] }}</td><td class="small muted">{{ $f['help'] ?? '' }}{{ isset($f['default']) && ! in_array($f['type'], ['ref']) ? ' Default for new records: '.$f['default'].'.' : '' }}</td></tr>@endforeach
</tbody></table></div></div>
<script>
(function () {
    var labels = document.querySelectorAll('.src-tabs label');
    function sync() { var v = (document.querySelector('input[name=source]:checked') || {}).value; labels.forEach(function (l) { l.classList.toggle('on', l.querySelector('input').checked); }); document.querySelectorAll('.src').forEach(function (s) { s.classList.toggle('on', s.getAttribute('data-src') === v); }); }
    document.querySelectorAll('input[name=source]').forEach(function (r) { r.addEventListener('change', sync); }); sync();
    document.getElementById('impForm').addEventListener('submit', function () { var b = this.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Reading…'; });
})();
</script>
@endsection
