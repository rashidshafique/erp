@php
    $lifeClass = ['OK' => 'status-completed', 'APPROACHING_LIMIT' => 'status-on-hold'];
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
    $overdue = $toolLife['nextInspection'] && $toolLife['nextInspection']->isPast();
@endphp
<div class="panel">
    <div class="panel-header">
        <h2>Life &amp; maintenance</h2>
        <span class="muted small"><a href="{{ route('tooling-life.dashboard') }}">Life limits</a> · <a href="{{ route('tooling-maintenance.index') }}">Maintenance register</a></span>
    </div>
    <div class="panel-body">
        <dl class="tool-facts" style="margin-bottom:{{ $toolLife['limits']->isEmpty() ? '0' : '18px' }}">
            <div><dt>Condition</dt><dd>@if ($pattern->condition_grade)<span class="status {{ ['A' => 'status-completed', 'B' => 'status-on-hold'][$pattern->condition_grade] ?? 'status-cancelled' }}">{{ $pattern->condition_grade }} · {{ \App\Models\Pattern::CONDITIONS[$pattern->condition_grade] }}</span><span class="sub">graded {{ $pattern->condition_graded_on?->displayDate() }}</span>@else<span class="muted">Not graded</span><span class="sub"><a href="{{ route('patterns.edit', $pattern) }}">Grade it</a></span>@endif</dd></div>
            <div><dt>Last maintenance</dt><dd>{{ $toolLife['lastDone']?->completed_at?->displayDate() ?? '—' }}@if ($toolLife['lastDone'])<span class="sub">{{ ucfirst(strtolower(str_replace('_', ' ', $toolLife['lastDone']->activity_type))) }} · {{ $toolLife['lastDone']->record_number }}</span>@endif</dd></div>
            <div><dt>Next inspection due</dt><dd @if ($overdue) style="color:var(--danger)" @endif>{{ $toolLife['nextInspection']?->displayDate() ?? '—' }}@if ($overdue)<span class="sub">overdue</span>@endif</dd></div>
            <div><dt>Open maintenance jobs</dt><dd>{{ $toolLife['openJobs'] }}</dd></div>
        </dl>
        @if ($toolLife['limits']->isEmpty())
            <p class="muted small" style="margin:12px 0 0">No service-life limit is set for this pattern. <a href="{{ route('tooling-life.create') }}">Set one</a> to track usage against its rated life.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Applies to</th><th>Used / rated life</th><th>Since last inspection</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($toolLife['limits'] as $row)
                            @php $limit = $row['limit']; $unit = strtolower(str_replace('_', ' ', $limit->usage_unit)); @endphp
                            <tr>
                                <td>{{ $limit->patternRevision ? 'Revision '.($limit->patternRevision->code ?? $limit->patternRevision->revision) : 'Whole pattern' }}</td>
                                <td class="mono">{{ $qty($row['current_usage']) }} / {{ $limit->service_life_quantity !== null ? $qty($limit->service_life_quantity) : '—' }} {{ $unit }}@if ($row['life_percent'] !== null) <span class="muted">({{ round($row['life_percent']) }}%)</span>@endif</td>
                                <td class="mono">{{ $qty($row['usage_since_inspection']) }}{{ $limit->inspection_interval_quantity !== null ? ' / '.$qty($limit->inspection_interval_quantity) : '' }}@if ($row['inspection_percent'] !== null) <span class="muted">({{ round($row['inspection_percent']) }}%)</span>@endif</td>
                                <td><span class="status {{ $lifeClass[$row['status']] ?? 'status-cancelled' }}">{{ $row['status_label'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

