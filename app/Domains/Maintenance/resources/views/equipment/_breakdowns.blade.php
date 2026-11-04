{{-- Breakdowns tab: the latest breakdowns with how long each kept the asset down. --}}
<div class="el-tools"><span class="el-note">{{ $monthBreakdowns->count() }} in {{ $monthName }} · {{ $hours($downtimeHours) }} h down</span><button type="button" class="el-btn" data-quick="qd-breakdown">Log breakdown</button></div>
<div class="el-table">
    @if ($breakdowns->isEmpty())
        <div class="el-note" style="padding:24px 12px">No breakdowns logged.</div>
    @else
    <table>
        <thead><tr><th>Breakdown</th><th>Asset</th><th>Cause</th><th>Started</th><th class="el-r">Downtime</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($breakdowns as $b)
            <tr>
                <td><strong>{{ $b->breakdown_number }}</strong><div class="el-sub">{{ $severities[$b->severity] ?? $b->severity }}</div></td>
                <td>{{ $b->equipment?->code }}</td>
                <td>{{ $b->failure_description }}@if ($b->failure_category)<div class="el-sub">{{ $categories[$b->failure_category] ?? $b->failure_category }}</div>@endif</td>
                <td>{{ $b->started_at->displayShortDate() }}</td>
                <td class="el-r">{{ $hours($b->downtimeHours()) }} h</td>
                <td><span class="el-pill {{ $b->restored_at ? '' : 'bad' }}">{{ $b->restored_at ? 'Restored' : 'Down' }}</span></td>
                <td class="el-r">@unless ($b->restored_at)<button type="button" class="el-btn" data-quick="qd-restore" data-act="{{ route('equipment-maintenance.breakdowns.restore', $b) }}" data-title="Restore {{ $b->breakdown_number }}">Restore</button>@endunless</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
