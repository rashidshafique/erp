@php
    $class = ['OPEN' => 'status-on-hold', 'IN_PROGRESS' => 'status-on-hold', 'AWAITING_VERIFICATION' => 'status-on-hold', 'CLOSED' => 'status-completed', 'CANCELLED' => 'status-cancelled'][$capa->status] ?? '';
@endphp
<span class="status {{ $class }}">{{ \App\Models\CapaAction::STATUSES[$capa->status] ?? $capa->status }}</span>@if ($capa->isOverdue()) <span class="status status-cancelled">Overdue</span>@endif
