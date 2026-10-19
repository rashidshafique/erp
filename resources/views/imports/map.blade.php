@extends('layouts.app')
@section('title', 'Match columns')
@section('breadcrumb')<a href="{{ route('imports.index') }}">Import data</a> / {{ $def['label'] }} / Match columns @endsection
@section('content')
<style>.mp td { vertical-align: top; } .mp select, .mp input { max-width: 100%; } .req { color: var(--danger); font-weight: 700; } .auto { font-size: 11px; color: var(--accent); } .dup { background: #fff3f3; } .sample td, .sample th { white-space: nowrap; font-size: 12.5px; } input[type=checkbox] { accent-color: var(--accent); }</style>
@php
    $letter = function ($i) { $s = ''; $i++; while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m).$s; $i = intdiv($i - $m - 1, 26); } return $s; };
    $first = $sample[0][1] ?? [];
    $sampleBy = fn ($i) => collect($sample)->map(fn ($r) => $r[1][$i] ?? '')->filter(fn ($v) => $v !== '')->first() ?? '';
@endphp
<div class="page-heading"><div><h1>Match columns to fields</h1><p>{{ $b->source_label }} · {{ $rowCount }} data rows. Choose which column feeds each field; anything you leave out is simply not imported.</p></div>
<div class="actions"><form method="POST" action="{{ route('imports.destroy', $b) }}" onsubmit="return confirm('Discard this import?')">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Discard</button></form></div></div>

<div class="panel"><div class="panel-body">
<form method="GET" action="{{ route('imports.map', $b) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    @if (count($sheets) > 1)<div class="field"><label>Sheet</label><select name="sheet" onchange="this.form.submit()">@foreach ($sheets as $i => $nm)<option value="{{ $i }}" @selected($b->sheet_index === $i)>{{ $nm }}</option>@endforeach</select></div>@endif
    <div class="field"><label>Headings are on row</label><input type="number" min="1" name="header" value="{{ $b->header_row }}" style="width:90px"></div>
    <button class="btn" type="submit">Reload columns</button>
    @if ($templates->isNotEmpty())<div class="field"><label>Use a saved mapping</label><select name="template" onchange="this.form.submit()"><option value="">— suggest from the headings —</option>@foreach ($templates as $t)<option value="{{ $t->id }}" @selected($usedTemplate?->id === $t->id)>{{ $t->name }}</option>@endforeach</select></div>@endif
</form></div></div>

<form method="POST" action="{{ route('imports.map.save', $b) }}">@csrf
<input type="hidden" name="sheet_index" value="{{ $b->sheet_index }}"><input type="hidden" name="header_row" value="{{ $b->header_row }}">
<div class="panel"><div class="panel-header"><h2>Fields</h2><span class="muted small">{{ count($headers) }} columns found</span></div>
<div class="table-wrap"><table class="mp"><thead><tr><th>Field</th><th>Take it from column</th><th>Example from your data</th><th>Fixed value</th></tr></thead><tbody>
@foreach ($def['fields'] as $k => $f)
@php $m = $mapping[$k]; @endphp
<tr data-field="{{ $k }}"><td><strong>{{ $f['label'] }}</strong>@if (! empty($f['required']))<span class="req" title="Needed for new records"> *</span>@endif<div class="muted small">{{ $f['help'] ?? '' }}</div></td>
<td><select name="mapping[{{ $k }}][col]" class="colsel"><option value="">— not imported —</option>@foreach ($headers as $i => $h)<option value="{{ $i }}" data-ex="{{ $sampleBy($i) }}" @selected(($m['col'] ?? null) === $i)>{{ $letter($i) }} · {{ $h !== '' ? $h : '(no heading)' }}</option>@endforeach</select>@if ($m['auto'])<div class="auto">matched by heading</div>@endif</td>
<td class="small ex">{{ $m['col'] !== null ? $sampleBy($m['col']) : '' }}</td>
<td>@if ($f['type'] === 'enum')<select name="mapping[{{ $k }}][default]"><option value="">—</option>@foreach ($f['enum'] as $ev)<option value="{{ $ev }}" @selected(($m['default'] ?? null) === $ev)>{{ $ev }}</option>@endforeach</select>
@elseif ($f['type'] === 'bool')<select name="mapping[{{ $k }}][default]"><option value="">—</option><option value="1" @selected(($m['default'] ?? null) === '1')>Yes</option><option value="0" @selected(($m['default'] ?? null) === '0')>No</option></select>
@else<input name="mapping[{{ $k }}][default]" value="{{ $m['default'] ?? '' }}" placeholder="{{ isset($f['default']) ? 'new records: '.$f['default'] : '' }}">@endif</td></tr>
@endforeach
</tbody></table></div>
<div class="panel-body muted small">A fixed value is used when the cell is blank or no column is chosen. For new records, blank fields get the default shown in grey. Blank cells never erase existing data.</div></div>

<div class="panel"><div class="panel-header"><h2>How to import</h2></div><div class="panel-body"><div class="form-grid">
    <div class="field"><label>Existing records</label><select name="mode"><option value="UPSERT" @selected($opt['mode'] === 'UPSERT')>Add new and update existing</option><option value="CREATE" @selected($opt['mode'] === 'CREATE')>Only add new (skip ones that exist)</option><option value="UPDATE" @selected($opt['mode'] === 'UPDATE')>Only update existing (skip new)</option></select></div>
    <div class="field"><label>A row is the same record when its … matches</label><select name="match_by">@foreach ($def['match'] as $mk => $ml)<option value="{{ $mk }}" @selected($opt['match_by'] === $mk)>{{ $ml }}</option>@endforeach</select>@if (count($def['match']) > 1)<div class="help">Code is safest. Name is allowed when your sheet has no codes.</div>@endif</div>
    <div class="field"><label>Dates are written</label><select name="date_order"><option value="DMY" @selected($opt['date_order'] === 'DMY')>Day first (31/01/2024)</option><option value="MDY" @selected($opt['date_order'] === 'MDY')>Month first (01/31/2024)</option></select></div>
    <div class="field"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="decimal_comma" value="1" @checked(! empty($opt['decimal_comma']))> Numbers use a decimal comma (1.234,5)</label>
        <label style="display:flex;gap:8px;align-items:center;margin-top:6px"><input type="checkbox" name="stop_on_error" value="1" @checked(! empty($opt['stop_on_error']))> Import nothing if any row has an error</label></div>
    <div class="field field-full"><label>Save this mapping as (optional)</label><input name="save_as" placeholder="e.g. Pattern register from stores" value="{{ old('save_as') }}"><div class="help">Reuse it next time for a file with the same headings.</div></div>
</div></div></div>

<div class="panel"><div class="panel-header"><h2>First rows of your data</h2></div><div class="table-wrap"><table class="sample"><thead><tr><th>Row</th>@foreach ($headers as $i => $h)<th>{{ $letter($i) }} · {{ $h }}</th>@endforeach</tr></thead><tbody>
@foreach ($sample as [$n, $cells])<tr><td class="muted">{{ $n }}</td>@foreach ($headers as $i => $h)<td>{{ \Illuminate\Support\Str::limit($cells[$i] ?? '', 40) }}</td>@endforeach</tr>@endforeach
</tbody></table></div></div>
<p><button class="btn btn-primary" type="submit">Check the data (nothing is saved yet)</button> <a class="btn" href="{{ route('imports.index') }}">Back</a></p>
</form>
<script>
(function () {
    var rows = document.querySelectorAll('.mp tbody tr');
    function refresh() {
        var seen = {};
        rows.forEach(function (tr) { var s = tr.querySelector('.colsel'); var o = s.options[s.selectedIndex]; tr.querySelector('.ex').textContent = o && o.value !== '' ? (o.getAttribute('data-ex') || '') : ''; if (s.value !== '') seen[s.value] = (seen[s.value] || 0) + 1; });
        rows.forEach(function (tr) { var s = tr.querySelector('.colsel'); tr.classList.toggle('dup', s.value !== '' && seen[s.value] > 1); });
    }
    rows.forEach(function (tr) { tr.querySelector('.colsel').addEventListener('change', refresh); }); refresh();
})();
</script>
@endsection
