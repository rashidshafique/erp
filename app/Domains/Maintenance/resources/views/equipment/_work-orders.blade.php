{{-- Work orders tab (design 7k): the open work orders, most urgent first. --}}
<div class="el-table">
    @if ($openWorkOrders->isEmpty())
        <div class="el-note" style="padding:24px 12px">No open work orders. Plans create them when they fall due, or start one with New work order.</div>
    @else
    <table>
        <thead><tr><th>Work order</th><th>Asset</th><th>Type</th><th>Due</th><th>Assigned</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($openWorkOrders as $wo)
            <tr>
                <td><strong>{{ $wo->work_order_number }}</strong>@if ($wo->priority !== 'NORMAL')<div class="el-sub">{{ $priorities[$wo->priority] ?? $wo->priority }} priority</div>@endif</td>
                <td>{{ $wo->equipment?->code }}<div class="el-sub">{{ $wo->equipment?->name }}@if ($wo->plan) · {{ $wo->plan->name }}@endif</div></td>
                <td>{{ $workOrderTypes[$wo->maintenance_type] ?? $wo->maintenance_type }}@if ($wo->plan)<div class="el-sub">{{ $wo->plan->cadenceLabel() }}</div>@endif</td>
                <td>{{ $wo->dueText() }}</td>
                <td>{{ $wo->assigned_to ?: '—' }}</td>
                <td><span class="el-pill {{ $pill($wo->dueState()) }}">{{ $wo->dueLabel() }}</span></td>
                <td class="el-r" style="white-space:nowrap">
                    @if ($wo->status === 'OPEN' || $wo->status === 'ON_HOLD')
                        <form method="POST" action="{{ route('equipment-maintenance.work-orders.start', $wo) }}" style="display:inline">@csrf<button class="el-btn" type="submit">Start</button></form>
                    @endif
                    <button type="button" class="el-btn" data-quick="qd-complete" data-act="{{ route('equipment-maintenance.work-orders.complete', $wo) }}" data-title="Complete {{ $wo->work_order_number }}">Complete</button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
