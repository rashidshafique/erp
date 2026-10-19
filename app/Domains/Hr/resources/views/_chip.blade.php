@php
    $map = ['ONBOARDING' => 'status-on-hold', 'PROBATION' => 'status-in-progress', 'ACTIVE' => 'status-completed', 'NOTICE' => 'status-on-hold', 'EXITED' => 'status-cancelled',
        'DRAFT' => 'status-on-hold', 'PUBLISHED' => 'status-completed', 'RETIRED' => 'status-cancelled', 'INITIATED' => 'status-on-hold', 'COMPLETED' => 'status-completed', 'CANCELLED' => 'status-cancelled'];
    $names = config('hr.statuses') + ['INITIATED' => 'Open', 'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled', 'DRAFT' => 'Draft', 'PUBLISHED' => 'Published', 'RETIRED' => 'Retired'];
@endphp
<span class="status {{ $map[$s] ?? '' }}">{{ $names[$s] ?? $s }}</span>
