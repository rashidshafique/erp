{{-- The rail (design 7k): open breakdowns, meters against their next service, and what the month's jobs cost. --}}
<div>
    <div class="el-h" style="margin-bottom:6px">Open breakdowns</div>
    @forelse ($unresolvedBreakdowns as $b)
        <div class="el-row"><span>{{ $b->equipment?->code }} · {{ $b->failure_description }}</span><span>{{ $hours($b->downtimeHours()) }} h</span></div>
    @empty
        <div class="el-note">No open breakdowns.</div>
    @endforelse
</div>
<div>
    <div class="el-h" style="margin-bottom:8px">Meters</div>
    @forelse ($meterPlans as $plan)
        @php $m = $plan->meter(); $used = (float) $m->current_reading; $due = (float) $plan->next_due_meter; $pct = $due > 0 ? min(100, round($used / $due * 100)) : 0; @endphp
        <div class="el-meter" style="margin-bottom:10px">
            <div class="el-meter-line"><span>{{ $plan->equipment?->code }} {{ strtolower($m->name) }}</span><span>{{ $fmt($used) }} / {{ $fmt($due) }}</span></div>
            <div class="el-track"><div class="el-fill @if ($pct >= 90) warn @endif" style="width:{{ $pct }}%"></div></div>
        </div>
    @empty
        <div class="el-note">No meter plans. Add a meter to an asset, then a plan by meter.</div>
    @endforelse
</div>
<div>
    <div class="el-h" style="margin-bottom:6px">Spares used, {{ $monthName }}</div>
    <div class="el-row"><span>Parts issued</span><span>{{ $fmt($partsIssued) }}</span></div>
    <div class="el-row"><span>Contractor</span><span>{{ $fmt($contractorCost) }}</span></div>
</div>
