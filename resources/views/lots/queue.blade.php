@extends('layouts.app')
@section('title', 'Production queue')
@section('content')
<div class="page-heading"><div><h1>Production queue</h1><p>Where each open lot stands after the pour. Oldest first within each step.</p></div><div class="actions">@if (Route::has('finished-goods.index'))<a class="btn" href="{{ route('finished-goods.index') }}">Finished goods ({{ $awaitingDispatch }} packed lines)</a>@endif</div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
@if(session("warning"))<div class="alert alert-warning">{{ session("warning") }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('lots.bulk-stage') }}">@csrf
@if($buckets)
<div class="panel"><div class="panel-body">
<strong>Move the ticked lots together</strong>
<div style="display:flex;gap:14px;flex-wrap:wrap;margin:8px 0">@foreach($stages as $st)<label><input type="checkbox" name="stages[]" value="{{ $st->id }}"> {{ $st->name }}</label>@endforeach</div>
<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
<input type="number" min="0" name="rejected" placeholder="Rejected (total)" style="width:150px" value="{{ old('rejected') }}">
<input name="rejection_reason" placeholder="Rejection reason" maxlength="100" value="{{ old('rejection_reason') }}">
<input name="operator_name" placeholder="Operator" maxlength="100" value="{{ old('operator_name') }}">
<input name="cooling_override_reason" placeholder="Reason, to shake out before cooling is met" maxlength="300">
<button class="btn btn-primary" type="submit" onclick="return confirm('Record the chosen stages for the ticked lots?')">Move ticked lots</button></div>
<p class="muted small" style="margin:8px 0 0">Every ticked lot passes the stages in order with all its pieces. Rejected pieces are taken from the oldest lots first.</p>
</div></div>
@endif
@forelse($buckets as $b)
<div class="panel"><div class="panel-header"><h2>{{ $b['label'] }}</h2><span class="muted">{{ count($b['lots']) }} lot(s)</span></div>
<div class="table-wrap"><table><thead><tr><th><input type="checkbox" class="bucket-all" aria-label="Select all in this step"></th><th>Lot</th><th>Product</th><th>Order</th><th>Pieces</th><th>Poured</th><th>Age</th><th>Cooling</th><th>Status</th></tr></thead><tbody>
@foreach($b['lots'] as $r)@php $l = $r['lot']; @endphp
<tr><td>@if($l->status === 'IN_PRODUCTION')<input type="checkbox" name="lots[]" value="{{ $l->id }}" class="queue-lot" aria-label="Select {{ $l->label }}">@endif</td><td><a href="{{ route('lots.show', $l) }}">{{ $l->label }}</a></td><td>{{ $l->product?->code }}</td><td>{{ $l->productionOrder?->production_order_number }}@if($r['urgent'])<div class="muted small">required {{ $r['urgent']->displayShortDate() }}</div>@endif</td>
<td>{{ $r['pieces'] }}</td><td>{{ $r['poured']?->displayShortDateTime() ?? '—' }}</td><td>{{ $r['age_h'] !== null ? $r['age_h'].' h' : '—' }}</td>
<td>@if($r['cool'])@if($r['cool']['wait'] > 0)<span class="status status-in-progress">wait {{ $r['cool']['wait'] }} min</span>@else <span class="muted small">met</span>@endif @endif</td>
<td>{{ $l->status }}</td></tr>@endforeach</tbody></table></div></div>
@empty<div class="panel"><div class="panel-body muted">No open lots.</div></div>@endforelse
</form>
<script>
document.querySelectorAll('.bucket-all').forEach(h => h.addEventListener('change', e => {
    e.target.closest('table').querySelectorAll('.queue-lot').forEach(b => b.checked = e.target.checked);
}));
</script>
@endsection
