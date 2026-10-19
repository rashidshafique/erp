@php
    $class = ['DRAFT' => 'status-on-hold', 'IN_REVIEW' => 'status-on-hold', 'REJECTED' => 'status-cancelled', 'APPROVED' => 'status-on-hold', 'EFFECTIVE' => 'status-completed', 'ACTIVE' => 'status-completed', 'SUPERSEDED' => 'status-cancelled', 'OBSOLETE' => 'status-cancelled'][$status] ?? '';
    $label = ($labels ?? \App\Domains\Documents\Models\ControlledDocumentRevision::STATUSES)[$status] ?? $status;
@endphp
<span class="status {{ $class }}">{{ $label }}</span>
