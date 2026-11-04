{{-- Plans tab: every active plan with its cadence, next due date or meter reading and where it stands. --}}
<div class="el-tools"><span class="el-note">{{ $plans->count() }} active {{ \Illuminate\Support\Str::plural('plan', $plans->count()) }}</span><button type="button" class="el-btn" data-quick="qd-plan">New plan</button></div>
<div class="el-table">
    @if ($plans->isEmpty())
        <div class="el-note" style="padding:24px 12px">No plans yet. Add equipment, then a plan by calendar or by meter.</div>
    @else
    <table>
        <thead><tr><th>Plan</th><th>Asset</th><th>Type</th><th>Every</th><th>Next due</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($plans as $plan)
            <tr>
                <td><strong>{{ $plan->code }}</strong><div class="el-sub">{{ $plan->name }}</div></td>
                <td>{{ $plan->equipment?->code }}<div class="el-sub">{{ $plan->equipment?->name }}</div></td>
                <td>{{ $planTypes[$plan->maintenance_type] ?? $plan->maintenance_type }}</td>
                <td>{{ $plan->cadenceLabel() }}</td>
                <td>@if ($plan->next_due_at){{ $plan->next_due_at->displayShortDate() }}@endif @if ($plan->next_due_meter !== null)<div class="el-sub">at {{ $fmt($plan->next_due_meter) }} {{ $plan->meter()?->shortUnit() }}</div>@endif @if (! $plan->next_due_at && $plan->next_due_meter === null)—@endif</td>
                <td><span class="el-pill {{ $pill($plan->dueState()) }}">{{ $plan->dueLabel() }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
