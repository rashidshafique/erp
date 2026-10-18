@php
    $label = \App\Services\Quality\CalibrationStatus::LABELS[$state] ?? $state;
    $class = ['IN_DATE' => 'status-completed', 'NOT_REQUIRED' => '', 'DUE_SOON' => 'status-on-hold', 'OVERDUE' => 'status-cancelled', 'NOT_CALIBRATED' => 'status-cancelled', 'OUT_OF_SERVICE' => 'status-cancelled', 'RETIRED' => ''][$state] ?? '';
@endphp
<span class="status {{ $class }}">{{ $label }}</span>
