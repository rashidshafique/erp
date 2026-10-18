@extends('layouts.app')
@section('title', 'Approvals')
@section('content')
<div class="page-heading"><div><h1>Approvals</h1><p>Requests raised when someone tries an action that needs sign-off. Once approved, the person repeats the action and it goes through once.</p></div>
<a class="btn" href="{{ route('approval-policies.index') }}">Approval rules</a></div>
<div class="panel"><div class="panel-body"><form method="GET">@foreach(['OPEN'=>'Open','PENDING'=>'Waiting','APPROVED'=>'Approved, not yet used','REJECTED'=>'Rejected','USED'=>'Used','CANCELLED'=>'Cancelled','ALL'=>'All'] as $k=>$l)<a class="btn {{ $status===$k?'btn-primary':'' }}" href="?status={{ $k }}">{{ $l }}</a> @endforeach</form></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Action</th><th>For</th><th>Raised by</th><th>Status</th><th>Decisions</th><th></th></tr></thead><tbody>
@forelse($requests as $r)<tr><td>{{ $r->actionLabel() }}<div class="muted small">{{ $r->created_at->displayShortDateTime() }}</div></td><td>{{ $r->subject_label }}</td><td>{{ $r->requested_by_name ?? '—' }}</td>
<td>{{ $r->status }}<div class="muted small">{{ $r->decisions->where('decision','APPROVE')->count() }}/{{ $r->required_approvals }} approvals</div></td>
<td>@foreach($r->decisions as $d)<div class="small">{{ $d->decision }} · {{ $d->user_name ?? 'anonymous' }}{{ $d->comment ? ': '.$d->comment : '' }}</div>@endforeach</td>
<td>@if($r->status==='PENDING')<form method="POST" action="{{ route('approvals.decide', $r) }}" style="display:flex;gap:6px;flex-wrap:wrap">@csrf<input name="comment" placeholder="Comment (required to reject)"><button class="btn btn-primary" name="decision" value="APPROVE">Approve</button><button class="btn" name="decision" value="REJECT">Reject</button></form>@endif
@if(in_array($r->status,['PENDING','APPROVED']))<form method="POST" action="{{ route('approvals.cancel', $r) }}" style="margin-top:6px">@csrf<button class="btn btn-small">Cancel request</button></form>@endif</td></tr>
@empty<tr><td colspan="6" class="muted">Nothing here.</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $requests->links() }}</div></div>
@endsection
