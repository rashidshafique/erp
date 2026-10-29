@extends('layouts.app')
@section('title', 'Quick QC')
@section('content')
<style>.valign-top td, .valign-top th { vertical-align: top; }</style>
<div class="page-heading"><div><h1>Quick QC</h1><p>Count the castings checked per product. The counts are shared over the open lots, oldest first, and recorded as inspections.</p></div><div class="actions"><a class="btn" href="{{ route('lots.index') }}">Casting lots</a><a class="btn" href="{{ route('inspections.index') }}">Inspections</a></div></div>
@if($onHold > 0)<div class="alert alert-warning">{{ $onHold }} lot(s) are on hold and are not listed. <a href="{{ route('lots.index', ['status' => 'ON_HOLD']) }}">Review and release them</a>.</div>@endif
@if($products->isEmpty())
<div class="panel"><div class="panel-body muted">No castings are waiting to be checked.</div></div>
@else
<form method="POST" action="{{ route('quick-qc.store') }}">@csrf
<div class="panel"><div class="table-wrap"><table class="valign-top"><thead><tr><th>Product</th><th>Lots</th><th>To check</th><th>Accepted</th><th>Rework</th><th>Rejected</th><th>Defects found (by cavity)</th></tr></thead><tbody>
@foreach($products as $revisionId => $row)
<tr>
<td style="min-width:180px;white-space:normal">{{ $row['product']?->code }} {{ $row['product']?->name }}<div class="muted">Rev {{ $row['revision']?->revision }}</div></td>
<td style="width:220px;max-width:220px;white-space:normal;word-break:break-word;line-height:1.5">@foreach($row['lots'] as $lot)<a href="{{ route('lots.show', $lot) }}">{{ $lot->label }}</a>@if(! $loop->last), @endif @endforeach</td>
<td><strong>{{ $row['to_check'] }}</strong></td>
@if($row['has_plan'])
<td colspan="4" class="muted">This product has an approved inspection plan. Use <a href="{{ route('lots.show', $row['lots']->first()) }}">the lot</a> to start a full inspection.</td>
@else
<td><input type="number" min="0" max="{{ $row['to_check'] }}" name="rows[{{ $revisionId }}][accepted]" value="{{ old("rows.$revisionId.accepted") }}" style="width:80px"></td>
<td><input type="number" min="0" max="{{ $row['to_check'] }}" name="rows[{{ $revisionId }}][rework]" value="{{ old("rows.$revisionId.rework") }}" style="width:80px"></td>
<td><input type="number" min="0" max="{{ $row['to_check'] }}" name="rows[{{ $revisionId }}][rejected]" value="{{ old("rows.$revisionId.rejected") }}" style="width:80px"></td>
<td><div class="qc-marks" data-next="{{ max(3, count(old("rows.$revisionId.marks", []))) }}">
@foreach(range(0, max(3, count(old("rows.$revisionId.marks", []))) - 1) as $n)
@php $m = "rows.$revisionId.marks.$n"; @endphp
<div class="qc-mark" style="display:flex;gap:6px;flex-wrap:wrap;margin:0 0 6px">
<select name="rows[{{ $revisionId }}][marks][{{ $n }}][defect_id]" aria-label="Defect" style="width:150px"><option value="">Defect…</option>@foreach($defects as $defect)<option value="{{ $defect->id }}" @selected(old("$m.defect_id") === $defect->id)>{{ $defect->name }}</option>@endforeach</select>
<input type="number" min="1" name="rows[{{ $revisionId }}][marks][{{ $n }}][quantity]" value="{{ old("$m.quantity") }}" placeholder="pcs" style="width:64px" aria-label="Pieces">
<select name="rows[{{ $revisionId }}][marks][{{ $n }}][disposition]" aria-label="Reject or rework"><option value="REJECT">reject</option><option value="REWORK" @selected(old("$m.disposition") === 'REWORK')>rework</option></select>
@if(($cavities[$revisionId] ?? collect())->isNotEmpty())<select name="rows[{{ $revisionId }}][marks][{{ $n }}][cavity]" aria-label="Cavity"><option value="">any cavity</option>@foreach($cavities[$revisionId] as $c)<option value="{{ $c['value'] }}" @selected(old("$m.cavity") === $c['value'])>{{ $c['label'] }}</option>@endforeach</select>@endif
<input name="rows[{{ $revisionId }}][marks][{{ $n }}][location]" value="{{ old("$m.location") }}" maxlength="150" placeholder="where (optional)" style="width:130px" aria-label="Where on the casting">
@if($pieceIds !== 'OFF')<input name="rows[{{ $revisionId }}][marks][{{ $n }}][piece_ids]" value="{{ old("$m.piece_ids") }}" maxlength="2000" placeholder="piece IDs{{ $pieceIds === 'REQUIRED' ? '' : ' (optional)' }}" style="width:150px" aria-label="Piece IDs">@endif
</div>
@endforeach
<button type="button" class="btn btn-small" data-add-mark>+ line</button>
</div></td>
@endif
</tr>
@endforeach
</tbody></table></div>
<div class="panel-body"><label>Inspector <input type="text" name="inspector_name" value="{{ old('inspector_name') }}" maxlength="150"></label> <button class="btn btn-primary" type="submit">Save quality check</button></div></div>
</form>
@endif
@if($byCavity->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Defects by cavity, last 30 days</h2><span class="muted small">Where a pattern keeps making the same defect.</span></div>
<div class="table-wrap"><table><thead><tr><th>Pattern cavity</th><th>Defect</th><th>Pieces</th><th>Rejected</th></tr></thead><tbody>
@foreach($byCavity as $r)<tr><td>{{ $r['mark']->cavityLabel() }}</td><td>{{ $r['mark']->defect?->name }}</td><td>{{ $r['quantity'] }}</td><td>{{ $r['rejected'] }}</td></tr>@endforeach
</tbody></table></div></div>
@endif
<script>
document.querySelectorAll('[data-add-mark]').forEach(function (button) {
    button.addEventListener('click', function () {
        var box = button.closest('.qc-marks'), lines = box.querySelectorAll('.qc-mark'), copy = lines[lines.length - 1].cloneNode(true), n = +box.dataset.next;
        copy.querySelectorAll('[name]').forEach(function (f) { f.name = f.name.replace(/\[marks\]\[\d+\]/, '[marks][' + n + ']'); if (f.tagName === 'SELECT') { f.selectedIndex = 0; } else { f.value = ''; } });
        box.dataset.next = n + 1;
        box.insertBefore(copy, button);
    });
});
</script>
@endsection
