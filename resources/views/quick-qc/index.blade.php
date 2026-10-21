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
<div class="panel"><div class="table-wrap"><table class="valign-top"><thead><tr><th>Product</th><th>Lots</th><th>To check</th><th>Accepted</th><th>Rework</th><th>Rejected</th><th>Rejected by defect</th></tr></thead><tbody>
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
<td><details><summary class="muted">Defects (optional)</summary>
@foreach($defects as $defect)<label style="display:flex;gap:8px;align-items:center;margin:4px 0"><input type="number" min="0" name="rows[{{ $revisionId }}][defects][{{ $defect->id }}]" value="{{ old("rows.$revisionId.defects.$defect->id") }}" style="width:70px"><span>{{ $defect->name }}</span></label>@endforeach
</details></td>
@endif
</tr>
@endforeach
</tbody></table></div>
<div class="panel-body"><label>Inspector <input type="text" name="inspector_name" value="{{ old('inspector_name') }}" maxlength="150"></label> <button class="btn btn-primary" type="submit">Save quality check</button></div></div>
</form>
@endif
@endsection
