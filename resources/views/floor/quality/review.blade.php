@extends('layouts.app')

@section('title', 'Result '.$lot->label)
@section('breadcrumb', 'Quality / '.$lot->label.' / Result')

{{-- design: Q·5A release (partial shipments allowed) and Q·5B hold (full lots only). The customer's rule picks the path. --}}
@section('content')
<div class="fl-tablet fl-has-tabs">
    @include('floor.quality._frame', ['active' => 'lots'])

    <h1 style="margin:0;font-size:22px">{{ $lot->label }} · result</h1>
    <p style="color:var(--fl-meta);font-size:13.5px;margin:4px 0 10px">{{ $lot->product?->name }} · {{ $total }} pcs checked</p>

    <div class="fl-rows">
        <x-floor.row title="Accepted" :age="(string) $counts['accepted']" />
        <x-floor.row title="Rework" :age="(string) $counts['rework']" />
        <x-floor.row title="Rejected" :age="(string) $counts['rejected']" />
        <x-floor.row :title="'Customer rule'.($rule['customer'] ? ' · '.$rule['customer'] : '')"
            :meta="$rule['order'] ? $rule['order'].' · partial shipments '.($rule['partial'] ? 'allowed' : 'not allowed').($rule['min'] ? ' · fewest to ship '.$rule['min'].' pcs' : '') : 'No sales order on this lot: an open NCR holds the whole lot'" />
    </div>

    <p style="margin:10px 0"><strong>Rejects and rework {{ $counts['rejected'] + $counts['rework'] }} of {{ $total }} = {{ $percent }}%</strong>
        · {{ $raises ? 'a nonconformance report is raised for them' : 'no nonconformance report is needed' }} ({{ strtolower(str_replace('_', ' ', $threshold)) }} in Quality rules).</p>

    @if ($path === 'A')
        <p>The lot will show {{ $counts['accepted'] }} ready{{ $counts['rework'] ? ' · '.$counts['rework'].' in rework' : '' }}{{ $counts['rejected'] ? ' · '.$counts['rejected'].' on the NCR' : '' }}. An open NCR covers only its own pieces and does not block the {{ $counts['accepted'] }} accepted.</p>
    @elseif ($holds)
        <p>This customer takes full lots only. The accepted {{ $counts['accepted'] }} pieces wait: the NCR{{ $counts['rework'] ? ' and the rework' : '' }} hold the whole lot.@if ($min) Needs {{ $min }} to ship.@endif It clears by itself when the NCR is closed.</p>
    @else
        <p>The lot is released: {{ $counts['accepted'] + $counts['rework'] }} pieces can ship.</p>
    @endif
    @if ($unreachable)<x-floor.blocker state="warn" title="{{ $min }} can no longer be reached" detail="Only {{ $counts['accepted'] + $counts['rework'] }} good pieces are left. Ask the planner to remake." />@endif

    <form method="POST" action="{{ route('floor.quality.lot.inspect.store', $lot) }}">@csrf
        @foreach ($counts as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        @foreach ($marks as $i => $m)<input type="hidden" name="defects[{{ $i }}][id]" value="{{ $m['defect_id'] }}"><input type="hidden" name="defects[{{ $i }}][qty]" value="{{ $m['quantity'] }}"><input type="hidden" name="defects[{{ $i }}][kind]" value="{{ $m['disposition'] }}">@endforeach
        <x-floor.big-button :label="$holds ? 'Hold lot, waiting for rework' : ($path === 'A' ? 'Release '.$counts['accepted'].($raises ? ' and raise NCR' : '') : 'Release lot')" :tone="$holds ? 'warn' : null" />
    </form>
    <p><a class="fl-btn" href="{{ route('floor.quality.lot.inspect', $lot) }}">Change the counts</a></p>
</div>
@endsection
