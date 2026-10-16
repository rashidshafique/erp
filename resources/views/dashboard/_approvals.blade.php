@php
    $u = auth()->user();
    $waiting = \App\Models\ApprovalRequest::where('status', 'PENDING')->when($u && ! $u->isAdmin(), fn ($q) => $q->where('requested_by_id', '!=', $u->id)->whereIn('permission', $u->permissions()))->count();
    $ready = $u ? \App\Models\ApprovalRequest::where('status', 'APPROVED')->where('requested_by_id', $u->id)->count() : \App\Models\ApprovalRequest::where('status', 'APPROVED')->count();
@endphp
@if ($waiting || $ready)
    <div class="alert alert-success" style="border-color:#e6c36a;background:#fff8e1;color:#7a5b00;border-radius:12px;margin-bottom:16px">
        @if ($waiting)<strong>{{ $waiting }}</strong> approval{{ $waiting === 1 ? '' : 's' }} waiting for a decision.@endif
        @if ($ready)<strong>{{ $ready }}</strong> approved — repeat the action to go ahead.@endif
        <a href="{{ route('approvals.index') }}">Open approvals →</a>
    </div>
@endif
