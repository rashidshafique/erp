@php
    use App\Domains\Hr\Models\AttendanceRecord as AR;
    $span = max(1, $hi - $lo);
    $pos = fn ($m) => round(100 * ($m - $lo) / $span, 2);
    $clock = fn ($m) => sprintf('%02d:%02d', intdiv(((int) $m) % 1440, 60), ((int) $m) % 60);
    $st = config('hr.attendance.day_status');
    $mon = ['Pending' => 'PENDING'];
    $ini = strtoupper(mb_substr($e->first_name, 0, 1).mb_substr((string) $e->last_name, 0, 1));
    $ticks = range(intdiv($lo, 60), intdiv($hi, 60), max(1, (int) round(($hi - $lo) / 60 / 6)));
@endphp
<div class="hr-dh" data-person="{{ $e->id }}">
    <span class="hr-avatar" aria-hidden="true">{{ $ini }}</span>
    <div style="min-width:0"><h2>{{ $e->full_name }} <span class="hr-chip">{{ ucfirst(strtolower($e->status)) }}</span></h2>
        <div class="sub">{{ $e->code }} &middot; {{ $e->designation?->name ?? 'No title' }}{{ $e->department ? ' · '.$e->department->name : '' }}{{ $e->work_email ? ' · '.$e->work_email : '' }}</div></div>
    <div class="hr-nav"><button type="button" data-step="-1" aria-label="Previous person">&lsaquo;</button><button type="button" data-step="1" aria-label="Next person">&rsaquo;</button><span data-pos></span><button type="button" data-close-drawer aria-label="Close">&times;</button></div>
</div>
<div class="hr-tabs" role="tablist"><button type="button" class="on" data-pane="a">Attendance</button><button type="button" data-pane="o">Overtime</button><button type="button" data-pane="t">Time off</button></div>

<div class="hr-pane on" data-p="a">
    <div class="hr-tools">
        <select data-days aria-label="Range">@foreach ([7, 14, 30] as $n)<option value="{{ $n }}" @selected($days === $n)>Last {{ $n }} days</option>@endforeach</select>
        <span class="muted small">{{ $from->format('M d') }} &ndash; {{ $to->format('M d, Y') }}</span>
        <a class="btn btn-small" href="{{ route('hr.employees.show', $e) }}">Open employee</a>
    </div>
    <div class="hr-mini">@foreach ($cards as $c)
        <div><div class="l">{{ $c['label'] }}</div><div class="n">{{ $c['n'] }}
            @if ($c['delta'] !== null)<span class="hr-d {{ $c['delta'] == 0 ? 'flat' : (($c['delta'] > 0) === $c['good'] ? 'up' : 'down') }}">{{ $c['delta'] > 0 ? '+' : '' }}{{ $c['delta'] }}% vs previous</span>@endif</div></div>
    @endforeach</div>
    <div class="hr-key"><span><i style="background:#7c3aed"></i>Working time</span><span><i style="background:#f59e0b"></i>Overtime</span><span><i style="background:#ef4444"></i>Late</span></div>
    @foreach ($rows as $row)
        @php $r = $row['rec']; $b = $row['bar']; @endphp
        <div class="tl-row">
            <div class="tl-head">{{ $row['date']->isToday() ? 'Today' : ($row['date']->isYesterday() ? 'Yesterday' : $row['date']->format('l, d M')) }}
                <span>@if ($r && $r->worked_minutes)Duration: {{ AR::hm($r->worked_minutes) }}@elseif ($r){{ $st[$r->status] ?? $r->status }}@else No record @endif</span></div>
            @if ($b)
                <div class="tl-main">
                    <div class="tl-t"><small>Clock-in</small>{{ $r->check_in->format('g:i A') }}</div>
                    <div><div class="tl-axis">@foreach ($ticks as $h)<span style="left:{{ $pos($h * 60) }}%">{{ sprintf('%02d:00', $h % 24) }}</span>@endforeach</div>
                        <div class="tl-track">
                            @if ($b['late'] > 0)<div class="tl-seg l" style="left:{{ $pos($b['in'] - $b['late']) }}%;width:{{ round(100 * $b['late'] / $span, 2) }}%" title="Late {{ $b['late'] }} min"></div>@endif
                            @if ($b['out'])
                                <div class="tl-seg w" style="left:{{ $pos($b['in']) }}%;width:{{ round(100 * max(0, $b['out'] - $b['in'] - $b['ot']) / $span, 2) }}%" title="Working time"></div>
                                @if ($b['ot'] > 0)<div class="tl-seg o" style="left:{{ $pos($b['out'] - $b['ot']) }}%;width:{{ round(100 * $b['ot'] / $span, 2) }}%" title="Overtime {{ AR::hm($b['ot']) }}"></div>@endif
                            @else
                                <div class="tl-seg w" style="left:{{ $pos($b['in']) }}%;width:1.2%" title="Still in, or no check-out"></div>
                            @endif
                        </div></div>
                    <div class="tl-t r"><small>Clock-out</small>{{ $r->check_out ? $r->check_out->format('g:i A') : 'Not yet' }}</div>
                </div>
                @if ($r->note)<div class="tl-note">{{ $r->note }}</div>@endif
            @else
                <div class="tl-track off" title="{{ $r ? ($st[$r->status] ?? '') : 'No record' }}"></div>
                @if ($r && $r->note)<div class="tl-note">{{ $r->note }}</div>@endif
            @endif
        </div>
    @endforeach
    <p class="muted small" style="margin-top:6px">Lunch and tea breaks are not punched in this system, so they are not drawn. Worked time already has the shift's break taken off.</p>
</div>

<div class="hr-pane" data-p="o">
    @if ($overtime->isEmpty())<div class="empty-state">No overtime in this range.</div>@else
    <table class="hr-tbl"><thead><tr><th>Day</th><th>Overtime</th><th>Approved</th><th>Status</th></tr></thead><tbody>
    @foreach ($overtime as $r)<tr><td>{{ $r->work_date->format('D, d M') }}</td><td>{{ AR::hm($r->overtime_minutes) }}</td><td>{{ AR::hm($r->overtime_approved_minutes) }}</td><td><span class="status {{ ['APPROVED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'PENDING' => 'status-on-hold'][$r->overtime_status] ?? '' }}">{{ ucfirst(strtolower($r->overtime_status)) }}</span></td></tr>@endforeach
    </tbody></table>
    <p style="margin-top:12px"><a class="btn btn-small" href="{{ route('hr.attendance.overtime') }}">Approve overtime</a></p>@endif
</div>

<div class="hr-pane" data-p="t">
    @if ($leaves->isEmpty())<div class="empty-state">No leave in this range.</div>@else
    <table class="hr-tbl"><thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th></tr></thead><tbody>
    @foreach ($leaves as $l)<tr><td>{{ $l->type->name }}</td><td>{{ $l->from_date->displayShortDate() }}</td><td>{{ $l->to_date->displayShortDate() }}</td><td>{{ rtrim(rtrim(\App\Support\Formats::number($l->days, 2), '0'), '.') }}{{ $l->half_day ? ' (half)' : '' }}</td><td><span class="status {{ ['APPROVED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'PENDING' => 'status-on-hold'][$l->status] ?? '' }}">{{ ucfirst(strtolower($l->status)) }}</span></td></tr>@endforeach
    </tbody></table>@endif
    <p style="margin-top:12px"><a class="btn btn-small" href="{{ route('hr.leave') }}">Leave balances</a></p>
</div>
